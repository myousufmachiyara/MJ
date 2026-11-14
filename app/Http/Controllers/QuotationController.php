<?php

namespace App\Http\Controllers;

use App\Models\Quotation;
use App\Models\PurchaseInvoiceItem;
use App\Models\Product;
use App\Models\Purity;
use App\Models\ChartOfAccounts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\myPDF;
use Carbon\Carbon;

/**
 * QUOTATION MODULE
 *
 * A copy of the Sale Invoice screens used for the initial pitch to a customer.
 * It is a DOCUMENT FOR RECORD ONLY:
 *
 *   - no Voucher / AccountingEntry is ever created (no ledger hit)
 *   - nothing is marked sold, nothing is settled against a consignment
 *     (no inventory effect) — barcode scan / name search only READ stock
 *   - it has its own tables (quotations, quotation_items, ...) so it can
 *     never show up in sale reports, profit reports or customer balances
 *
 * Pricing maths (rate x purity weight, making, parts, item / invoice
 * discount, VAT on making, invoice VAT) is identical to SaleInvoiceController
 * so a quotation shows the same numbers the invoice would.
 *
 * Payment method, cheque / bank-transfer / material-payment fields and the
 * consignment link are intentionally NOT part of a quotation.
 */
class QuotationController extends Controller
{
    // =========================================================================
    // INDEX
    // =========================================================================

    public function index()
    {
        $quotations = Quotation::with('customer', 'attachments')->orderByDesc('id')->get();
        return view('quotations.index', compact('quotations'));
    }

    // =========================================================================
    // CREATE
    // =========================================================================

    public function create()
    {
        $customers = ChartOfAccounts::where('account_type', 'customer')->get();
        $products  = Product::with('measurementUnit')->get();
        $purities  = Purity::all();

        return view('quotations.create', compact('products', 'customers', 'purities'));
    }

    // =========================================================================
    // STORE
    // =========================================================================

    public function store(Request $request)
    {
        $this->validateQuotation($request);

        // Same retry-on-duplicate-number guard as SaleInvoiceController::store()
        $maxAttempts = 3;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                DB::beginTransaction();

                $isTaxable = $request->boolean('is_taxable');

                $quotation = Quotation::create([
                    'quotation_no'             => $this->generateQuotationNo(),
                    'is_taxable'               => $isTaxable,
                    'customer_id'              => $request->customer_id,
                    'quotation_date'           => $request->quotation_date,
                    'remarks'                  => $request->remarks,
                    'currency'                 => $request->currency,
                    'exchange_rate'            => $request->exchange_rate,
                    'gold_rate_usd'            => $request->gold_rate_usd,
                    'gold_rate_aed_ounce'      => $request->gold_rate_aed_ounce,
                    'gold_rate_aed'            => $request->gold_rate_aed,
                    'diamond_rate_usd'         => $request->diamond_rate_usd,
                    'diamond_rate_aed'         => $request->diamond_rate_aed,
                    'purchase_gold_rate_aed'   => $request->purchase_gold_rate_aed,
                    'purchase_making_rate_aed' => $request->purchase_making_rate_aed,
                    'net_amount'               => 0,
                    'net_amount_aed'           => 0,
                    'vat_percent'              => 0,
                    'vat_amount'               => 0,
                    'grand_total'              => 0,
                    'created_by'               => auth()->id(),
                ]);

                $this->createItems($quotation, $request->items, $request);
                $this->finalizeTotals($quotation, $request, $isTaxable);
                $this->storeAttachments($request, $quotation);

                DB::commit();

                return redirect()
                    ->route('quotations.index')
                    ->with('success', 'Quotation #' . $quotation->quotation_no . ' saved successfully.');

            } catch (\Throwable $e) {
                DB::rollBack();

                if ($this->isDuplicateQuotationNoError($e) && $attempt < $maxAttempts) {
                    continue;
                }

                Log::error('Quotation Store Error', [
                    'message' => $e->getMessage(),
                    'line'    => $e->getLine(),
                    'file'    => $e->getFile(),
                    'trace'   => $e->getTraceAsString(),
                    'attempt' => $attempt,
                ]);
                return back()->withInput()->with('error', 'Error: ' . $e->getMessage());
            }
        }
    }

    private function isDuplicateQuotationNoError(\Throwable $e): bool
    {
        return $e instanceof \Illuminate\Database\QueryException
            && (int) ($e->errorInfo[1] ?? 0) === 1062
            && str_contains($e->getMessage(), 'quotation_no');
    }

    // =========================================================================
    // EDIT
    // =========================================================================

    public function edit($id)
    {
        $quotation = Quotation::with(['items' => fn ($q) => $q->orderBy('id'), 'items.parts' => fn ($q) => $q->orderBy('id'), 'attachments'])->findOrFail($id);
        $purities  = Purity::all();
        $customers = ChartOfAccounts::where('account_type', 'customer')->get();
        $products  = Product::with('measurementUnit')->get();

        $goldAedOunce    = ($quotation->gold_rate_aed    ?? 0) * 31.1035;
        $diamondAedOunce = ($quotation->diamond_rate_aed ?? 0) * 31.1035;

        // Display only: current selling price + picture of each line's purchased item.
        $barcodes = $quotation->items->pluck('barcode_number')->filter()->unique()->values();

        $sellingPrices = PurchaseInvoiceItem::whereIn('barcode_number', $barcodes)
            ->orderBy('id')
            ->get(['barcode_number', 'selling_price', 'selling_price_usd'])
            ->keyBy('barcode_number');

        $itemImages = PurchaseInvoiceItem::whereIn('barcode_number', $barcodes)
            ->whereNotNull('image_path')
            ->orderBy('id')
            ->pluck('image_path', 'barcode_number');

        $itemsData = $quotation->items->map(function ($item) use ($sellingPrices, $itemImages) {
            $sp = $sellingPrices->get($item->barcode_number);
            return [
                'image_url'         => $this->imageUrlFromPath($itemImages->get($item->barcode_number)),
                'item_name'         => $item->item_name,
                'barcode_number'    => $item->barcode_number,
                'selling_price_aed' => $sp && $sp->selling_price !== null ? (float) $sp->selling_price : null,
                'selling_price_usd' => $sp && $sp->selling_price_usd !== null ? (float) $sp->selling_price_usd : null,
                'product_id'        => $item->product_id,
                'item_description'  => $item->item_description,
                'purity'            => $item->purity,
                'gross_weight'      => $item->gross_weight,
                'making_rate'       => $item->making_rate,
                'material_type'     => $item->material_type,
                'discount_type'     => $item->discount_type ?? null,
                'discount_value'    => (float) ($item->discount_value ?? 0),
                'discount_amount'   => (float) ($item->discount_amount ?? 0),
                'vat_percent'       => $item->vat_percent,
                'purity_weight'     => $item->purity_weight,
                'col_995'           => $item->col_995,
                'making_value'      => $item->making_value,
                'material_value'    => $item->material_value,
                'taxable_amount'    => $item->taxable_amount,
                'vat_amount'        => $item->vat_amount,
                'item_total'        => $item->item_total,
                'flat_price'        => $item->selling_price,
                'parts' => $item->parts->map(function ($part) {
                    return [
                        'item_name'        => $part->item_name,
                        'product_id'       => $part->product_id,
                        'part_description' => $part->part_description,
                        'qty'              => $part->qty,
                        'rate'             => $part->rate,
                        'stone_qty'        => $part->stone_qty,
                        'stone_rate'       => $part->stone_rate,
                        'total'            => $part->total,
                    ];
                })->values()->toArray(),
            ];
        })->values()->toArray();

        return view('quotations.edit', compact(
            'quotation', 'customers', 'products',
            'itemsData', 'goldAedOunce', 'diamondAedOunce', 'purities'
        ));
    }

    // =========================================================================
    // UPDATE
    // =========================================================================

    public function update(Request $request, $id)
    {
        $quotation = Quotation::findOrFail($id);

        $this->validateQuotation($request);

        try {
            DB::beginTransaction();

            $isTaxable = $request->boolean('is_taxable');

            $quotation->update([
                'is_taxable'               => $isTaxable,
                'customer_id'              => $request->customer_id,
                'quotation_date'           => $request->quotation_date,
                'remarks'                  => $request->remarks,
                'currency'                 => $request->currency,
                'exchange_rate'            => $request->exchange_rate,
                'gold_rate_usd'            => $request->gold_rate_usd,
                'gold_rate_aed_ounce'      => $request->gold_rate_aed_ounce,
                'gold_rate_aed'            => $request->gold_rate_aed,
                'diamond_rate_usd'         => $request->diamond_rate_usd,
                'diamond_rate_aed'         => $request->diamond_rate_aed,
                'purchase_gold_rate_aed'   => $request->purchase_gold_rate_aed,
                'purchase_making_rate_aed' => $request->purchase_making_rate_aed,
            ]);

            foreach ($quotation->items as $oldItem) {
                $oldItem->parts()->delete();
            }
            $quotation->items()->delete();

            $this->createItems($quotation, $request->items, $request);
            $this->finalizeTotals($quotation, $request, $isTaxable);
            $this->storeAttachments($request, $quotation);

            DB::commit();

            return redirect()
                ->route('quotations.index')
                ->with('success', 'Quotation #' . $quotation->quotation_no . ' updated successfully.');

        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Quotation Update Error', [
                'message' => $e->getMessage(),
                'line'    => $e->getLine(),
                'file'    => $e->getFile(),
                'trace'   => $e->getTraceAsString(),
            ]);
            return back()->withInput()->with('error', 'Error: ' . $e->getMessage());
        }
    }

    // =========================================================================
    // DESTROY — a quotation has no voucher / inventory to reverse
    // =========================================================================

    public function destroy($id)
    {
        $quotation = Quotation::findOrFail($id);

        try {
            $quotation->delete();
            return redirect()->route('quotations.index')->with('success', 'Quotation deleted.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Error: ' . $e->getMessage());
        }
    }


    // =========================================================================
    // PRINT — optional product pictures
    // =========================================================================

    /**
     * FEATURE (product picture on the printed invoice): the Sale Invoice list
     * asks "show product pictures?" before opening either print; "Yes" adds
     * ?images=1 to the print URL. Without it (or with images=0) both prints
     * come out exactly as before.
     */
    private function wantsPrintImages(Request $request): bool
    {
        return $request->boolean('images');
    }

    /**
     * barcode_number => small data-URI thumbnail (or null) for every item of
     * the invoice. The picture is the one uploaded on the purchased item that
     * carries the same barcode (Purchase Invoice -> Img / bulk picture import).
     *
     * Each picture is shrunk to a 240px square JPEG (letter-boxed on white, so
     * nothing is cropped or stretched) before it goes into the PDF - the
     * stored originals can be 1600px and would make the PDF huge. If GD is not
     * available the original file is used as it is.
     */
    private function printImageSources($invoice): array
    {
        $barcodes = $invoice->items->pluck('barcode_number')->filter()->unique()->values();
        if ($barcodes->isEmpty()) {
            return [];
        }

        $paths = PurchaseInvoiceItem::whereIn('barcode_number', $barcodes)
            ->whereNotNull('image_path')
            ->orderBy('id')
            ->pluck('image_path', 'barcode_number');

        $out = [];
        foreach ($paths as $barcode => $relPath) {
            $out[$barcode] = $this->thumbnailForPdf($relPath);
        }

        return $out;
    }

    private function thumbnailForPdf(string $relPath): ?string
    {
        try {
            $full = \Illuminate\Support\Facades\Storage::disk('public')->path($relPath);
            if (!is_file($full)) {
                return null;
            }

            if (!function_exists('imagecreatetruecolor')) {
                return $full; // no GD: embed the original file
            }

            $info = @getimagesize($full);
            if (!$info) {
                return null;
            }

            switch ($info[2]) {
                case IMAGETYPE_JPEG: $src = @imagecreatefromjpeg($full); break;
                case IMAGETYPE_PNG:  $src = @imagecreatefrompng($full);  break;
                case IMAGETYPE_GIF:  $src = @imagecreatefromgif($full);  break;
                case IMAGETYPE_WEBP: $src = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($full) : false; break;
                default:             $src = false;
            }
            if (!$src) {
                return null;
            }

            $w = imagesx($src);
            $h = imagesy($src);
            $box = 240;
            $scale = min($box / $w, $box / $h, 1);
            $nw = max(1, (int) round($w * $scale));
            $nh = max(1, (int) round($h * $scale));

            $dst = imagecreatetruecolor($box, $box);
            imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
            imagecopyresampled($dst, $src, (int) (($box - $nw) / 2), (int) (($box - $nh) / 2), 0, 0, $nw, $nh, $w, $h);

            ob_start();
            imagejpeg($dst, null, 80);
            $jpeg = ob_get_clean();
            imagedestroy($src);
            imagedestroy($dst);

            return 'data:image/jpeg;base64,' . base64_encode($jpeg);
        } catch (\Throwable $e) {
            Log::warning('[Quotation] print picture skipped: ' . $e->getMessage());
            return null;
        }
    }

    /** <img> (or a dash) for the picture column of a print row. */
    private function printImageCell(?string $src, int $sizePx = 34): string
    {
        if (!$src) {
            return '-';
        }
        return '<img src="' . $src . '" width="' . $sizePx . '" height="' . $sizePx . '">';
    }

    public function print(Request $request, $id)
    {
        $invoice = Quotation::with([
            'customer',
            'items'       => fn ($q) => $q->orderBy('id'),
            'items.product.measurementUnit',
            'items.parts' => fn ($q) => $q->orderBy('id'),
            'items.parts.product.measurementUnit',
        ])->findOrFail($id);

        $totalMaterialAed = $invoice->items->sum('material_value');
        $totalMakingAed   = $invoice->items->sum('making_value');
        $totalVatAed      = $invoice->items->sum('vat_amount');
        $totalTaxableAed  = $invoice->items->sum('taxable_amount');

        $totalDiamondVal = $invoice->items->sum(function ($item) {
            return $item->parts->sum(fn($p) => $p->qty * $p->rate);
        });
        $totalStoneVal = $invoice->items->sum(function ($item) {
            return $item->parts->sum(fn($p) => ($p->stone_qty ?? 0) * ($p->stone_rate ?? 0));
        });
        $totalPartsAed = $invoice->items->sum(function ($item) {
            return $item->parts->sum('total');
        });

        // FEATURE (discount): item-wise discounts (sale_invoice_items.discount_amount)
        // plus the optional invoice-level one (sale_invoices.discount_amount).
        $totalItemDiscount    = (float) $invoice->items->sum(fn ($i) => (float) ($i->discount_amount ?? 0));
        $invoiceDiscount      = (float) ($invoice->discount_amount ?? 0);
        $totalDiscount        = $totalItemDiscount + $invoiceDiscount;

        $totalCurrencyPayable = $totalMakingAed + $totalPartsAed + $totalVatAed;

        // FEATURE (product picture column) - optional, see wantsPrintImages()
        $showImages = $this->wantsPrintImages($request);
        $imageSrc   = $showImages ? $this->printImageSources($invoice) : [];

        $pdf = new myPDF();
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetCreator('Your App');
        $pdf->SetTitle($invoice->quotation_no);
        $pdf->SetMargins(10, 10, 10);
        $pdf->setCellPadding(1.2);

        $pdf->AddPage();

        $logoPath = public_path('assets/img/mj-logo.png');
        $logoHtml = file_exists($logoPath) ? '<img src="' . $logoPath . '" width="85">' : '';
        $pdf->writeHTML('
            <table width="100%" cellpadding="3">
                <tr>
                    <td width="40%">' . $logoHtml . '</td>
                    <td width="60%" style="text-align:right;font-size:10px;">
                        <strong>YUNEEQ FINE JEWELRY L.L.C</strong><br>
                        Suite #M04, Mezzanine floor, Al Buteen 2 Building, Gold Souq. Gate no.1, Deira, Dubai<br>
                        TRN No: 104902647700003
                    </td>
                </tr>
            </table><hr>', true, false, false, false);

        $title = 'QUOTATION';
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 6, $title, 0, 1, 'C');
        $pdf->Ln(2);
        $pdf->SetFont('helvetica', '', 9);

        $goldRateUsdOz      = $invoice->gold_rate_usd        ?? 0;
        $goldRateAedOz      = $invoice->gold_rate_aed_ounce  ?? 0;
        $diamondRateDisplay = $invoice->currency === 'USD' ? $invoice->diamond_rate_usd : $invoice->diamond_rate_aed;

        $customerHtml = '
        <table cellpadding="3" width="100%">
            <tr>
                <td width="50%">
                    <b>To:</b><br>
                    ' . ($invoice->customer->name ?? '-') . '<br>
                    ' . ($invoice->customer->address ?? '-') . '<br>
                    Contact: ' . ($invoice->customer->contact_no ?? '-') . '<br>
                    TRN: ' . (trim((string) ($invoice->customer->trn ?? '')) !== '' ? e($invoice->customer->trn) : '-') . '<br>
                    Ref: ' . (trim((string) ($invoice->customer->remarks ?? '')) !== '' ? e($invoice->customer->remarks) : '-') . '<br>
                </td>
                <td width="50%">
                    <table border="1" cellpadding="3" width="100%">
                        <tr><td width="45%"><b>Date</b></td><td width="55%">' . Carbon::parse($invoice->quotation_date)->format('d.m.Y') . '</td></tr>
                        <tr><td><b>Quotation No</b></td><td>' . $invoice->quotation_no . '</td></tr>
                        <tr><td><b>Gold Rate (USD/oz)</b></td><td>' . number_format($goldRateUsdOz, 2)  . '</td></tr>
                        <tr><td><b>Gold Rate (AED/oz)</b></td><td>' . number_format($goldRateAedOz, 2)  . '</td></tr>
                        <tr><td><b>Gold Rate (AED/g)</b></td><td>'  . number_format($invoice->gold_rate_aed, 4) . '</td></tr>
                        <tr><td><b>Diamond Rate (' . $invoice->currency . '/Ct)</b></td><td>' . number_format($diamondRateDisplay, 2) . '</td></tr>
                    </table>
                </td>
            </tr>
        </table>';
        $pdf->writeHTML($customerHtml, true, false, false, false);

        // column widths (%): with the picture column Item Name / Description
        // give up some room so the table still adds up to the page width.
        $wName = $showImages ? 15 : 18;
        $wDesc = $showImages ? 18 : 22;
        $picTh = $showImages ? '<th width="7%">Picture</th>' : '';
        $x     = $showImages ? 1 : 0;   // extra column count for colspans below

        $html = '
        <table border="1" cellpadding="3" width="100%" style="font-size:8px;">
            <thead>
                <tr style="font-weight:bold;background-color:#f5f5f5;text-align:center;">
                    <th width="3%">#</th>
                    ' . $picTh . '
                    <th width="' . $wName . '%">Item Name</th>
                    <th width="' . $wDesc . '%">Description</th>
                    <th width="6%">Gross Wt</th>
                    <th width="6%">Purity</th>
                    <th width="6%">Purity Wt</th>
                    <th width="6%">995</th>
                    <th width="7%">Material</th>
                    <th width="8%">Material Val</th>
                    <th width="6%">MC</th>
                    <th width="5%">VAT%</th>
                    <th width="7%">Item Total</th>
                </tr>
            </thead>
            <tbody>';

        $detailCerts = $this->certificateMap($invoice->items->pluck('barcode_number'));

        foreach ($invoice->items as $index => $item) {
            $hasParts     = $item->parts && $item->parts->count() > 0;
            $productTotal = $item->item_total;

            $html .= '
                <tr style="text-align:center;background-color:#ffffff;">
                    <td width="3%">' . ($index + 1) . '</td>
                    ' . ($showImages ? '<td width="7%">' . $this->printImageCell($imageSrc[$item->barcode_number] ?? null, 32) . '</td>' : '') . '
                    <td width="' . $wName . '%">' . ($item->item_name ?: ($item->product->name ?? '-')) . $this->itemCodeHtml($item->barcode_number, $detailCerts->get($item->barcode_number)) . '</td>
                    <td width="' . $wDesc . '%">' . ($item->item_description ?? '-') . '</td>
                    <td width="6%">' . number_format($item->gross_weight, 3) . '</td>
                    <td width="6%">' . number_format($item->purity, 3) . '</td>
                    <td width="6%">' . number_format($item->purity_weight, 3) . '</td>
                    <td width="6%">' . number_format($item->col_995 ?? 0, 3) . '</td>
                    <td width="7%">' . ucfirst($item->material_type) . '</td>
                    <td width="8%">' . number_format($item->material_value, 2) . '</td>
                    <td width="6%">' . number_format($item->taxable_amount, 2) . '</td>
                    <td width="5%">' . number_format($item->vat_percent, 0) . '%</td>
                    <td width="7%" style="font-weight:bold;">' . number_format($item->item_total, 2) . '</td>
                </tr>';

            // FEATURE (discount, item-wise): shown as its own sub-row so the
            // 12-column table layout is unchanged.
            if ((float) ($item->discount_amount ?? 0) > 0) {
                $discLabel = ($item->discount_type ?? '') === 'percent'
                    ? 'Discount (' . rtrim(rtrim(number_format((float) $item->discount_value, 2), '0'), '.') . '%)'
                    : 'Discount';
                $html .= '<tr style="background-color:#fff8e6;font-style:italic;font-size:7px;">
                            <td></td><td colspan="' . (9 + $x) . '" align="right"><b>' . $discLabel . ' (already deducted from Item Total):</b></td>
                            <td colspan="2" align="right">-' . number_format((float) $item->discount_amount, 2) . '</td>
                          </tr>';
            }

            if ($hasParts) {
                $html .= '<tr style="background-color:#f9f9f9;font-style:italic;font-size:7px;">
                            <td></td><td colspan="' . (11 + $x) . '"><b>Parts Detail:</b></td>
                          </tr>';

                foreach ($item->parts as $part) {
                    $displayPartName = $part->item_name ?: ($part->product->name ?? 'Part');
                    $html .= '
                    <tr style="font-size:7px;background-color:#fcfcfc;text-align:center;">
                        <td width="3%"></td>
                        ' . ($showImages ? '<td width="7%"></td>' : '') . '
                        <td width="' . $wName . '%" style="text-align:left;">' . $displayPartName . '</td>
                        <td width="' . $wDesc . '%" style="text-align:left;">' . htmlspecialchars($part->part_description ?? '') . '</td>
                        <td colspan="3">' . number_format($part->qty, 3) . ' Ct</td>
                        <td colspan="3">St.' . number_format($part->stone_qty ?? 0, 2) . '</td>
                        <td colspan="3" style="font-weight:bold;" align="right">' . number_format($part->total, 2) . '</td>
                    </tr>';
                }

                $html .= '
                    <tr style="background-color:#eeeeee;font-weight:bold;font-size:8px;">
                        <td colspan="' . (10 + $x) . '" align="right">Product Grand Total (Material + MC + Parts + VAT):</td>
                        <td colspan="2" align="right">' . number_format($productTotal, 2) . '</td>
                    </tr>';
            }
        }

        $html .= '
                <tr style="font-weight:bold;background-color:#f5f5f5;">
                    <td colspan="' . (11 + $x) . '" align="right">Net Quotation Amount</td>
                    <td align="right">' . number_format($invoice->net_amount, 2) . '</td>
                </tr>
            </tbody>
        </table>';

        $pdf->writeHTML($html, true, false, false, false);

        $aedAmount = $invoice->currency === 'USD' ? $invoice->net_amount_aed : $invoice->net_amount;

        $printGrandTotal = ($invoice->vat_amount ?? 0) > 0
            ? ($invoice->grand_total ?? $aedAmount)
            : $aedAmount;

        $summaryHtml = '
        <table width="100%" cellpadding="0" border="0" style="margin-top:10px;">
            <tr>
                <td width="45%" valign="top">
                    <table border="1" cellpadding="4" width="100%" style="font-size:9px;">
                        <tr style="background-color:#f5f5f5;"><td><b>Remarks</b></td></tr>
                        <tr><td>' . nl2br(e($invoice->remarks ?: '-')) . '</td></tr>';

        $summaryHtml .= '</table>
                </td>
                <td width="10%"></td>
                <td width="45%" valign="top">
                    <table border="1" cellpadding="4" width="100%" style="font-size:9px;">
                        <tr style="background-color:#f5f5f5;"><td colspan="2" align="center"><b>Summary (' . $invoice->currency . ')</b></td></tr>
                        <tr><td width="60%">Material Value</td>              <td width="40%" align="right">' . number_format($totalMaterialAed, 2) . '</td></tr>
                        <tr><td>Diamond Parts Val.</td>                      <td align="right">' . number_format($totalDiamondVal, 2) . '</td></tr>
                        <tr><td>Stone Parts Val.</td>                        <td align="right">' . number_format($totalStoneVal, 2) . '</td></tr>
                        <tr><td>Making Charges (MC)</td>                     <td align="right">' . number_format($totalMakingAed, 2) . '</td></tr>
                        <tr><td>VAT on MC</td>                               <td align="right">' . number_format($totalVatAed, 2) . '</td></tr>'
                        . ($totalItemDiscount > 0 ? '<tr><td>Item Discounts</td><td align="right">-' . number_format($totalItemDiscount, 2) . '</td></tr>' : '')
                        . ($invoiceDiscount   > 0 ? '<tr><td>Invoice Discount' . (($invoice->discount_type ?? '') === 'percent' ? ' (' . rtrim(rtrim(number_format((float) $invoice->discount_value, 4), '0'), '.') . '%)' : '') . '</td><td align="right">-' . number_format($invoiceDiscount, 2) . '</td></tr>' : '')
                        . '
                        <tr style="font-weight:bold;background-color:#ddeeee;">
                            <td>Currency Payable (MC + Parts + VAT)</td>
                            <td align="right">' . number_format($totalCurrencyPayable, 2) . '</td>
                        </tr>
                        <tr style="font-weight:bold;background-color:#eeeeee;">
                            <td>Quotation Total</td>
                            <td align="right">' . number_format($invoice->net_amount, 2) . '</td>
                        </tr>';

        if ($invoice->currency === 'USD') {
            $summaryHtml .= '
                        <tr><td>Exchange Rate</td><td align="right">' . number_format($invoice->exchange_rate, 4) . '</td></tr>
                        <tr><td>Total (AED)</td><td align="right">' . number_format($aedAmount, 2) . '</td></tr>';
        } else {
            $summaryHtml .= '<tr><td>Total (AED)</td><td align="right">' . number_format($aedAmount, 2) . '</td></tr>';
        }

        if (($invoice->vat_amount ?? 0) > 0) {
            $summaryHtml .= '
                        <tr><td>Invoice VAT (' . number_format($invoice->vat_percent, 2) . '%)</td>
                            <td align="right">' . number_format($invoice->vat_amount, 2) . '</td></tr>
                        <tr style="font-weight:bold;background-color:#ddeedd;">
                            <td>Grand Total (AED)</td>
                            <td align="right">' . number_format($printGrandTotal, 2) . '</td>
                        </tr>';
        }

        $summaryHtml .= '</table></td></tr></table>';

        $pdf->Ln(2);
        $pdf->writeHTML($summaryHtml, true, false, false, false);

        $remainingSpace = $pdf->getPageHeight() - $pdf->GetY() - $pdf->getBreakMargin();
        if ($remainingSpace < 70) {
            $pdf->AddPage();
        }

        $pdf->Ln(2);
        $pdf->SetFont('helvetica', 'B', 9);
        $wordsAED = $pdf->convertCurrencyToWords($printGrandTotal, 'AED');
        if ($invoice->currency === 'USD') {
            $wordsUSD = $pdf->convertCurrencyToWords($invoice->net_amount, 'USD');
            $pdf->Cell(0, 5, 'Amount in Words (USD): ' . $wordsUSD, 0, 1, 'L');
            $pdf->Cell(0, 5, 'Amount in Words (AED): ' . $wordsAED, 0, 1, 'L');
        } else {
            $pdf->Cell(0, 5, 'Amount in Words (AED): ' . $wordsAED, 0, 1, 'L');
        }

        $pdf->Ln(2);
        $pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
        $pdf->Ln(2);
        $pdf->SetFont('helvetica', '', 9);
        $termsHtml = '
            <div style="line-height:8px;text-align:justify;color:#333;">
                <b>NOTE:</b> This is a quotation for information and record purposes only. It is not an invoice and does not constitute a sale. Prices are indicative and may change with gold / diamond market rates until the order is confirmed.
            </div>';
        $pdf->writeHTML($termsHtml, true, false, false, false);

        $pdf->Ln(26);
        $y = $pdf->GetY();
        $pdf->Line(20, $y, 80, $y);
        $pdf->Line(130, $y, 190, $y);
        $pdf->SetXY(20, $y);
        $pdf->Cell(50, 5, "Customer's Signature", 0, 0, 'C');
        $pdf->SetXY(130, $y);
        $pdf->Cell(50, 5, "Authorized Signature", 0, 0, 'C');

        return $pdf->Output($invoice->quotation_no . '.pdf', 'I');
    }

    // =========================================================================
    // EXPORT EXCEL — same layout as the detailed PDF invoice (print())
    //
    // FEATURE (Sale Invoice Excel export): one .xlsx per invoice that mirrors
    // the PDF printout block for block — company header, title, customer +
    // invoice/rates box, the items table (with the same two-row "Making"
    // header, Parts Detail sub-rows, per-item discount sub-row and Net
    // Invoice Amount row), then Payment Details and Summary side by side,
    // amount in words and the terms. Numbers are real numeric cells (with
    // number formats), so they can be summed/filtered in Excel.
    //
    // Needs phpoffice/phpspreadsheet — already installed as a dependency of
    // maatwebsite/excel. If it is ever missing, the user gets a clear message
    // instead of a crash.
    // =========================================================================

    public function exportExcel(Request $request, $id)
    {
        if (!class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class)) {
            return back()->with('error', 'Excel export needs the phpoffice/phpspreadsheet package. Run: composer require phpoffice/phpspreadsheet');
        }

        $invoice = Quotation::with([
            'customer',
            'items'       => fn ($q) => $q->orderBy('id'),
            'items.product.measurementUnit',
            'items.parts' => fn ($q) => $q->orderBy('id'),
            'items.parts.product.measurementUnit',
        ])->findOrFail($id);

        try {
            $spreadsheet = $this->buildInvoiceSpreadsheet($invoice, $this->wantsPrintImages($request));
        } catch (\Throwable $e) {
            Log::error('Quotation Excel Export Error', [
                'invoice_id' => $invoice->id,
                'message'    => $e->getMessage(),
                'line'       => $e->getLine(),
                'file'       => $e->getFile(),
            ]);
            return back()->with('error', 'Excel export failed: ' . $e->getMessage());
        }

        $filename = preg_replace('/[^A-Za-z0-9._-]/', '_', (string) $invoice->quotation_no) . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Places a product picture into an Excel cell (the "Picture" column of the
     * Excel export). $src is what printImageSources() returns: a data-URI
     * thumbnail, or a file path when GD is not available. Returns false when
     * nothing was placed - a picture is cosmetic and must never break the export.
     */
    private function addExcelPicture($sheet, ?string $src, string $cell, int $px = 56): bool
    {
        if (!$src) {
            return false;
        }

        try {
            if (str_starts_with($src, 'data:image')) {
                $bin = base64_decode(substr($src, strpos($src, ',') + 1), true);
                $gd  = $bin !== false ? @imagecreatefromstring($bin) : false;
                if (!$gd) {
                    return false;
                }
                $drawing = new \PhpOffice\PhpSpreadsheet\Worksheet\MemoryDrawing();
                $drawing->setImageResource($gd);
                $drawing->setRenderingFunction(\PhpOffice\PhpSpreadsheet\Worksheet\MemoryDrawing::RENDERING_JPEG);
                $drawing->setMimeType(\PhpOffice\PhpSpreadsheet\Worksheet\MemoryDrawing::MIMETYPE_JPEG);
                // thumbnails are letter-boxed squares, so a square box never distorts them
                $drawing->setResizeProportional(false);
                $drawing->setWidth($px);
                $drawing->setHeight($px);
            } else {
                if (!is_file($src)) {
                    return false;
                }
                $drawing = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing();
                $drawing->setPath($src);
                $drawing->setResizeProportional(true);
                $drawing->setHeight($px);
            }

            $drawing->setName('Item picture');
            $drawing->setOffsetX(8);
            $drawing->setOffsetY(4);
            $drawing->setCoordinates($cell);
            $drawing->setWorksheet($sheet);

            return true;
        } catch (\Throwable $e) {
            Log::warning('[Excel] item picture skipped: ' . $e->getMessage());
            return false;
        }
    }

    private function buildInvoiceSpreadsheet(Quotation $invoice, bool $showImages = false)
    {
        // optional "Picture" column (?images=1 on the export URL) - same pictures as the PDF
        $imageSrc = $showImages ? $this->printImageSources($invoice) : [];

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle(substr(preg_replace('/[\\\\\/\?\*\[\]:]/', '-', (string) $invoice->quotation_no), 0, 31) ?: 'Quotation');

        // ── totals — identical to print() ───────────────────────────────────
        $totalMaterial = (float) $invoice->items->sum('material_value');
        $totalMaking   = (float) $invoice->items->sum('making_value');
        $totalVat      = (float) $invoice->items->sum('vat_amount');
        $totalDiamond  = (float) $invoice->items->sum(fn ($i) => $i->parts->sum(fn ($p) => $p->qty * $p->rate));
        $totalStone    = (float) $invoice->items->sum(fn ($i) => $i->parts->sum(fn ($p) => ($p->stone_qty ?? 0) * ($p->stone_rate ?? 0)));
        $totalParts    = (float) $invoice->items->sum(fn ($i) => $i->parts->sum('total'));

        $totalItemDiscount = (float) $invoice->items->sum(fn ($i) => (float) ($i->discount_amount ?? 0));
        $invoiceDiscount   = (float) ($invoice->discount_amount ?? 0);
        $totalDiscount     = $totalItemDiscount + $invoiceDiscount;

        $currencyPayable = $totalMaking + $totalParts + $totalVat;

        $aedAmount  = $invoice->currency === 'USD' ? $invoice->net_amount_aed : $invoice->net_amount;
        $grandTotal = ($invoice->vat_amount ?? 0) > 0 ? ($invoice->grand_total ?? $aedAmount) : $aedAmount;

        $diamondRateDisplay = $invoice->currency === 'USD' ? $invoice->diamond_rate_usd : $invoice->diamond_rate_aed;

        // ── styling helpers ────────────────────────────────────────────────
        $thin = ['borders' => ['allBorders' => [
            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
            'color'       => ['rgb' => '808080'],
        ]]];
        $headFill = ['fill' => [
            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'F5F5F5'],
        ]];
        $totalFill = ['fill' => [
            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'EEEEEE'],
        ]];
        $center = ['alignment' => [
            'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
            'vertical'   => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            'wrapText'   => true,
        ]];
        $left = ['alignment' => [
            'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,
            'vertical'   => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            'wrapText'   => true,
        ]];
        $right = ['alignment' => [
            'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT,
            'vertical'   => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            'wrapText'   => true,
        ]];
        $bold   = ['font' => ['bold' => true]];
        $italic = ['font' => ['italic' => true, 'size' => 8]];

        $FMT_MONEY = '#,##0.00';
        $FMT_WT    = '0.000';

        // put(): write a value into a cell or merged range and optionally
        // style / number-format it. Strings are always written as text.
        $put = function (string $range, $value, array $styles = [], ?string $fmt = null) use ($sheet) {
            $first = explode(':', $range)[0];
            if (str_contains($range, ':')) {
                $sheet->mergeCells($range);
            }
            if (is_string($value)) {
                $sheet->setCellValueExplicit($first, $value, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            } else {
                $sheet->setCellValue($first, $value);
            }
            foreach ($styles as $style) {
                $sheet->getStyle($range)->applyFromArray($style);
            }
            if ($fmt !== null) {
                $sheet->getStyle($range)->getNumberFormat()->setFormatCode($fmt);
            }
        };

        // 14 columns A–N, matching the PDF's 14-column items table.
        $widths = ['A' => 5, 'B' => 22, 'C' => 24, 'D' => 10, 'E' => 9, 'F' => 10, 'G' => 9,
                   'H' => 10, 'I' => 11, 'J' => 10, 'K' => 12, 'L' => 10, 'M' => 8, 'N' => 13];
        foreach ($widths as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }
        if ($showImages) {
            $sheet->getColumnDimension('O')->setWidth(14); // Picture column sits after Item Total
        }
        $sheet->setShowGridlines(false); // Worksheet method (SheetView has no such setter)

        // ── header: logo (left) + company details (right) ─────────────────
        for ($r = 1; $r <= 4; $r++) {
            $sheet->getRowDimension($r)->setRowHeight(18);
        }
        $logoPath = public_path('assets/img/mj-logo.png');
        if (file_exists($logoPath)) {
            try {
                $drawing = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing();
                $drawing->setName('Logo');
                $drawing->setPath($logoPath);
                $drawing->setHeight(60);
                $drawing->setCoordinates('A1');
                $drawing->setWorksheet($sheet);
            } catch (\Throwable $e) {
                // logo is cosmetic — never let it break the export
            }
        }
        $put('H1:N4',
            "YUNEEQ FINE JEWELRY L.L.C\nSuite #M04, Mezzanine floor, Al Buteen 2 Building, Gold Souq. Gate no.1, Deira, Dubai\nTRN No: 104902647700003",
            [$right]);

        // ── title ─────────────────────────────────────────────────────────
        $put('A6:N6', $invoice->is_taxable ? 'QUOTATION' : 'QUOTATION', [$center, $bold]);
        $sheet->getStyle('A6')->getFont()->setSize(13);
        $sheet->getRowDimension(6)->setRowHeight(22);

        // ── customer (left) + invoice / rates box (right) ─────────────────
        $customer = $invoice->customer;
        $put('A8:F13',
            "To:\n" . ($customer->name ?? '-') . "\n" . ($customer->address ?? '-') .
            "\nContact: " . ($customer->contact_no ?? '-') . "\nTRN: " . ($customer->trn ?? '-'),
            [$left]);
        $sheet->getStyle('A8:F13')->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP);

        $metaRows = [
            ['Date',                                     \Carbon\Carbon::parse($invoice->quotation_date)->format('d.m.Y'), null],
            ['Invoice No',                               (string) $invoice->quotation_no,                                   null],
            ['Gold Rate (USD/oz)',                       (float) ($invoice->gold_rate_usd ?? 0),                          $FMT_MONEY],
            ['Gold Rate (AED/oz)',                       (float) ($invoice->gold_rate_aed_ounce ?? 0),                    $FMT_MONEY],
            ['Gold Rate (AED/g)',                        (float) ($invoice->gold_rate_aed ?? 0),                          '0.0000'],
            ['Diamond Rate (' . $invoice->currency . '/Ct)', (float) ($diamondRateDisplay ?? 0),                          $FMT_MONEY],
        ];
        $r = 8;
        foreach ($metaRows as [$label, $value, $fmt]) {
            $put("I{$r}:K{$r}", $label, [$thin, $bold, $left]);
            $put("L{$r}:N{$r}", $value, [$thin, $right], $fmt);
            $r++;
        }

        // ── items table header (two rows, "Making" spans Rate + Value) ─────
        $hr = 15;
        $headers = [
            'A' => '#', 'B' => 'Item Name', 'C' => 'Description', 'D' => 'Gross Wt', 'E' => 'Purity',
            'F' => 'Purity Wt', 'G' => '995', 'J' => 'Material', 'K' => 'Material Val',
            'L' => 'MC', 'M' => 'VAT%', 'N' => 'Item Total',
        ];
        foreach ($headers as $col => $text) {
            $put("{$col}{$hr}:{$col}" . ($hr + 1), $text, [$thin, $headFill, $bold, $center]);
        }
        $put("H{$hr}:I{$hr}", 'Making', [$thin, $headFill, $bold, $center]);
        $put('H' . ($hr + 1), 'Rate',  [$thin, $headFill, $bold, $center]);
        $put('I' . ($hr + 1), 'Value', [$thin, $headFill, $bold, $center]);
        if ($showImages) {
            $put("O{$hr}:O" . ($hr + 1), 'Picture', [$thin, $headFill, $bold, $center]);
        }

        // ── item rows ─────────────────────────────────────────────────────
        $row = $hr + 2;
        foreach ($invoice->items as $index => $item) {
            $itemRow = $row;
            $put("A{$row}", $index + 1, [$thin, $center]);
            $put("B{$row}", (string) ($item->item_name ?: ($item->product->name ?? '-')), [$thin, $left]);
            $put("C{$row}", (string) ($item->item_description ?? '-'), [$thin, $left]);
            $put("D{$row}", (float) $item->gross_weight,  [$thin, $center], $FMT_WT);
            $put("E{$row}", (float) $item->purity,        [$thin, $center], $FMT_WT);
            $put("F{$row}", (float) $item->purity_weight, [$thin, $center], $FMT_WT);
            $put("G{$row}", (float) ($item->col_995 ?? 0), [$thin, $center], $FMT_WT);
            $put("H{$row}", (float) ($item->making_rate ?? 0), [$thin, $center], $FMT_MONEY);
            $put("I{$row}", (float) $item->making_value,  [$thin, $center], $FMT_MONEY);
            $put("J{$row}", ucfirst((string) $item->material_type), [$thin, $center]);
            $put("K{$row}", (float) $item->material_value, [$thin, $center], $FMT_MONEY);
            $put("L{$row}", (float) $item->taxable_amount, [$thin, $center], $FMT_MONEY);
            $put("M{$row}", (float) $item->vat_percent / 100, [$thin, $center], '0%');
            $put("N{$row}", (float) $item->item_total,    [$thin, $center, $bold], $FMT_MONEY);
            if ($showImages) {
                $sheet->getStyle("O{$row}")->applyFromArray($thin);
                if ($this->addExcelPicture($sheet, $imageSrc[$item->barcode_number] ?? null, "O{$row}")) {
                    $sheet->getRowDimension($row)->setRowHeight(50);
                } else {
                    $put("O{$row}", '-', [$thin, $center]);
                }
            }
            $row++;

            // per-item discount sub-row (same as the PDF)
            if ((float) ($item->discount_amount ?? 0) > 0) {
                $discLabel = ($item->discount_type ?? '') === 'percent'
                    ? 'Discount (' . rtrim(rtrim(number_format((float) $item->discount_value, 2), '0'), '.') . '%) — already deducted from Item Total:'
                    : 'Discount — already deducted from Item Total:';
                $put("B{$row}:L{$row}", $discLabel, [$thin, $italic, $right]);
                $put("M{$row}:N{$row}", -1 * (float) $item->discount_amount, [$thin, $italic, $right], $FMT_MONEY);
                $sheet->getStyle("A{$row}:N{$row}")->applyFromArray(['fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'FFF8E6'],
                ]]);
                $row++;
            }

            // parts detail (same as the PDF)
            if ($item->parts && $item->parts->count() > 0) {
                $put("B{$row}:N{$row}", 'Parts Detail:', [$thin, $italic, $left]);
                $row++;

                foreach ($item->parts as $part) {
                    $partName = $part->item_name ?: ($part->product->name ?? 'Part');
                    $put("B{$row}", (string) $partName, [$thin, $italic, $left]);
                    $put("C{$row}", (string) ($part->part_description ?? ''), [$thin, $italic, $left]);
                    $put("D{$row}:E{$row}", (float) $part->qty, [$thin, $italic, $center], '0.000" Ct"');
                    $put("F{$row}:G{$row}", (float) $part->rate, [$thin, $italic, $center], '"Rate: "#,##0.00');
                    $put("H{$row}:I{$row}", (float) ($part->stone_qty ?? 0), [$thin, $italic, $center], '"St. "0.00');
                    $put("J{$row}:K{$row}", (float) ($part->stone_rate ?? 0), [$thin, $italic, $center], '"SR: "#,##0.00');
                    $put("L{$row}:N{$row}", (float) $part->total, [$thin, $italic, $right, $bold], $FMT_MONEY);
                    $sheet->getStyle("A{$row}")->applyFromArray($thin);
                    $row++;
                }

                $put("A{$row}:M{$row}", 'Product Grand Total (Material + MC + Parts + VAT):', [$thin, $totalFill, $bold, $right]);
                $put("N{$row}", (float) $item->item_total, [$thin, $totalFill, $bold, $right], $FMT_MONEY);
                $row++;
            }
        }

        $put("A{$row}:M{$row}", 'Net Quotation Amount', [$thin, $headFill, $bold, $right]);
        $put("N{$row}", (float) $invoice->net_amount, [$thin, $headFill, $bold, $right], $FMT_MONEY);
        $row += 2;

        // ── payment details (left) & summary (right), side by side ─────────
        $startRow = $row;

        $payRows = [['Remarks', (string) ($invoice->remarks ?? '-'), null]];

        $put("A{$row}:F{$row}", 'Details', [$thin, $headFill, $bold, $left]);
        $r = $row + 1;
        foreach ($payRows as [$label, $value, $fmt]) {
            $put("A{$r}:C{$r}", $label, [$thin, $left]);
            $put("D{$r}:F{$r}", $value, [$thin, $right], $fmt);
            $r++;
        }
        $leftEnd = $r;

        $sumRows = [
            ['Material Value',      $totalMaterial, false],
            ['Diamond Parts Val.',  $totalDiamond,  false],
            ['Stone Parts Val.',    $totalStone,    false],
            ['Making Charges (MC)', $totalMaking,   false],
            ['VAT on MC',           $totalVat,      false],
        ];
        if ($totalItemDiscount > 0) {
            $sumRows[] = ['Item Discounts', -1 * $totalItemDiscount, false];
        }
        if ($invoiceDiscount > 0) {
            $pct = ($invoice->discount_type ?? '') === 'percent'
                ? ' (' . rtrim(rtrim(number_format((float) $invoice->discount_value, 4), '0'), '.') . '%)'
                : '';
            $sumRows[] = ['Invoice Discount' . $pct, -1 * $invoiceDiscount, false];
        }
        $sumRows[] = ['Currency Payable (MC + Parts + VAT)', $currencyPayable, true];
        $sumRows[] = ['Quotation Total', (float) $invoice->net_amount, true];
        if ($invoice->currency === 'USD') {
            $sumRows[] = ['Exchange Rate', (float) $invoice->exchange_rate, false, '0.0000'];
        }
        $sumRows[] = ['Total (AED)', (float) $aedAmount, false];
        if (($invoice->vat_amount ?? 0) > 0) {
            $sumRows[] = ['Invoice VAT (' . number_format((float) $invoice->vat_percent, 2) . '%)', (float) $invoice->vat_amount, false];
            $sumRows[] = ['Grand Total (AED)', (float) $grandTotal, true];
        }

        $put("H{$row}:N{$row}", 'Summary (' . $invoice->currency . ')', [$thin, $headFill, $bold, $center]);
        $r = $row + 1;
        foreach ($sumRows as $sr) {
            $isBold = $sr[2];
            $fmt    = $sr[3] ?? $FMT_MONEY;
            $put("H{$r}:K{$r}", $sr[0], array_filter([$thin, $left, $isBold ? $bold : null, $isBold ? $totalFill : null]));
            $put("L{$r}:N{$r}", $sr[1], array_filter([$thin, $right, $isBold ? $bold : null, $isBold ? $totalFill : null]), $fmt);
            $r++;
        }
        $row = max($leftEnd, $r) + 1;

        // ── amount in words + terms + signatures (same text as the PDF) ───
        $words = new \App\Services\myPDF();
        $put("A{$row}:N{$row}", 'Amount in Words (AED): ' . $words->convertCurrencyToWords($grandTotal, 'AED'), [$left, $bold]);
        $row++;
        if ($invoice->currency === 'USD') {
            $put("A{$row}:N{$row}", 'Amount in Words (USD): ' . $words->convertCurrencyToWords($invoice->net_amount, 'USD'), [$left, $bold]);
            $row++;
        }
        $row++;

        $put("A{$row}:N{$row}",
            'NOTE: This is a quotation for information and record purposes only. It is not an invoice and does not constitute a sale. ' .
            'Prices are indicative and may change with gold / diamond market rates until the order is confirmed.',
            [$left]);
        $sheet->getRowDimension($row)->setRowHeight(48);
        $row += 3;

        $put("B{$row}:D{$row}", "Customer's Signature", [$center]);
        $sheet->getStyle("B{$row}:D{$row}")->getBorders()->getTop()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        $put("K{$row}:M{$row}", 'Authorized Signature', [$center]);
        $sheet->getStyle("K{$row}:M{$row}")->getBorders()->getTop()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

        // ── print setup: landscape A4, fit to one page wide ────────────────
        $sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
        $sheet->getPageSetup()->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4);
        $sheet->getPageSetup()->setFitToWidth(1);
        $sheet->getPageSetup()->setFitToHeight(0);
        $sheet->getPageSetup()->setFitToPage(true);

        return $spreadsheet;
    }


    public function printSimple(Request $request, $id)
    {
        $invoice = Quotation::with([
            'customer',
            'items'       => fn ($q) => $q->orderBy('id'),
            // FEATURE (parts on the B2C receipt): same relations print() loads.
            'items.parts' => fn ($q) => $q->orderBy('id'),
            'items.parts.product.measurementUnit',
        ])->findOrFail($id);

        // FEATURE (product picture column) - optional, see wantsPrintImages()
        $showImages = $this->wantsPrintImages($request);
        $imageSrc   = $showImages ? $this->printImageSources($invoice) : [];

        $pdf = new myPDF();
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetCreator('Yuneeq Fine Jewelry');
        $pdf->SetTitle($invoice->quotation_no . ' — Simple');
        $pdf->SetMargins(10, 10, 10);
        $pdf->setCellPadding(1.2);
        $pdf->AddPage();

        $logoPath = public_path('assets/img/mj-logo.png');
        $logoHtml = file_exists($logoPath) ? '<img src="' . $logoPath . '" width="85">' : '';

        $pdf->writeHTML('
            <table width="100%" cellpadding="3">
                <tr>
                    <td width="40%">' . $logoHtml . '</td>
                    <td width="60%" style="text-align:right;font-size:10px;">
                        <strong>YUNEEQ FINE JEWELRY L.L.C</strong><br>
                        Suite #M04, Mezzanine floor, Al Buteen 2 Building, Gold Souq. Gate no.1, Deira, Dubai<br>
                        TRN No: 104902647700003
                    </td>
                </tr>
            </table><hr>', true, false, false, false);

        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 6, 'QUOTATION', 0, 1, 'C');
        $pdf->Ln(2);
        $pdf->SetFont('helvetica', '', 9);

        $aedAmount = $invoice->currency === 'USD' ? $invoice->net_amount_aed : $invoice->net_amount;

        $receiptGrandTotal = ($invoice->vat_amount ?? 0) > 0
            ? ($invoice->grand_total ?? $aedAmount)
            : $aedAmount;

        $customerHtml = '
        <table cellpadding="3" width="100%">
            <tr>
                <td width="50%">
                    <b>To:</b><br>
                    ' . ($invoice->customer->name ?? 'Walk-in Customer') . '<br>
                    ' . ($invoice->customer->address ?? '') . '<br>
                    Contact: ' . ($invoice->customer->contact_no ?? '-') . '<br>
                    TRN: ' . (trim((string) ($invoice->customer->trn ?? '')) !== '' ? e($invoice->customer->trn) : '-') . '<br>
                    <b>Ref:</b> ' . (trim((string) ($invoice->customer->remarks ?? '')) !== '' ? e($invoice->customer->remarks) : '-') . '<br>
                </td>
                <td width="50%">
                    <table border="1" cellpadding="3" width="100%">
                        <tr><td width="45%"><b>Date</b></td><td width="55%">' . Carbon::parse($invoice->quotation_date)->format('d.m.Y') . '</td></tr>
                        <tr><td><b>Quotation No</b></td><td>' . $invoice->quotation_no . '</td></tr>
                        <tr><td><b>Currency</b></td><td>' . $invoice->currency . '</td></tr>
                    </table>
                </td>
            </tr>
        </table>';
        $pdf->writeHTML($customerHtml, true, false, false, false);

        // widths (%) - with the picture column Item Name / Description / Material
        // give up room so the table still adds up to 100 %.
        $wName = $showImages ? 26 : 30;
        $wDesc = $showImages ? 20 : 25;
        $wMat  = $showImages ? 8  : 10;
        $picTh = $showImages ? '<th width="11%">Picture</th>' : '';
        $x     = $showImages ? 1 : 0;

        $html = '
        <table border="1" cellpadding="3" width="100%" style="font-size:8px;">
            <thead>
                <tr style="font-weight:bold;background-color:#f5f5f5;text-align:center;">
                    <th width="5%">#</th>
                    ' . $picTh . '
                    <th width="' . $wName . '%" style="text-align:left;">Item Name</th>
                    <th width="' . $wDesc . '%">Description</th>
                    <th width="12%">Gross Wt (g)</th>
                    <th width="' . $wMat . '%">Material</th>
                    <th width="18%">Amount (AED)</th>
                </tr>
            </thead>
            <tbody>';

        $totalGrossWeight = 0;

        // FEATURE (item code + certificate on the B2C receipt): the item code
        // is the item's barcode_number (SKU); the certificate number is stored
        // on the purchased item carrying that same barcode.
        $certByBarcode = PurchaseInvoiceItem::whereIn(
                'barcode_number',
                $invoice->items->pluck('barcode_number')->filter()->unique()->values()
            )
            ->orderBy('id')
            ->pluck('certificate_no', 'barcode_number');

        foreach ($invoice->items as $i => $item) {
            $codeLines = [];
            if ($item->barcode_number) {
                $codeLines[] = 'Code: ' . htmlspecialchars($item->barcode_number);
            }
            $certNo = $certByBarcode->get($item->barcode_number);
            if ($certNo) {
                $codeLines[] = 'Cert#: ' . htmlspecialchars($certNo);
            }
            $codeHtml = $codeLines
                ? '<br><span style="font-size:7px;color:#555555;">' . implode(' &nbsp;|&nbsp; ', $codeLines) . '</span>'
                : '';

            $html .= '
                <tr style="text-align:center;background-color:#ffffff;">
                    <td width="5%">' . ($i + 1) . '</td>
                    ' . ($showImages ? '<td width="11%">' . $this->printImageCell($imageSrc[$item->barcode_number] ?? null, 38) . '</td>' : '') . '
                    <td width="' . $wName . '%" style="text-align:left;">' . htmlspecialchars($item->item_name ?: ($item->product->name ?? '-')) . $codeHtml . '</td>
                    <td width="' . $wDesc . '%" style="text-align:left;">' . htmlspecialchars($item->item_description ?? '-') . '</td>
                    <td width="12%">' . number_format($item->gross_weight, 3) . '</td>
                    <td width="' . $wMat . '%">' . ucfirst($item->material_type) . '</td>
                    <td width="18%" style="font-weight:bold;">' . number_format($item->item_total, 2) . '</td>
                </tr>';
            $totalGrossWeight += $item->gross_weight;

            // FEATURE (parts on the B2C receipt): the diamond / stone parts of
            // each item, listed right under it (same data as the detailed
            // invoice's "Parts Detail"). Their value is already inside the
            // item's Amount, so each part's total is informational.
            if ($item->parts && $item->parts->count() > 0) {
                $html .= '<tr style="background-color:#f9f9f9;font-style:italic;font-size:7px;">
                            <td></td><td colspan="' . (5 + $x) . '"><b>Parts Detail:</b></td>
                          </tr>';

                foreach ($item->parts as $part) {
                    $partName = htmlspecialchars($part->item_name ?: ($part->product->name ?? 'Part'));
                    $detail   = number_format($part->qty, 3) . ' Ct';
                    if ((float) ($part->stone_qty ?? 0) > 0) {
                        $detail .= '<br>Stone ' . number_format($part->stone_qty, 2);
                    }
                    $html .= '
                    <tr style="font-size:7px;background-color:#fcfcfc;text-align:center;">
                        <td width="5%"></td>
                        ' . ($showImages ? '<td width="11%"></td>' : '') . '
                        <td width="' . $wName . '%" style="text-align:left;">' . $partName . '</td>
                        <td width="' . $wDesc . '%" style="text-align:left;">' . htmlspecialchars($part->part_description ?? '') . '</td>
                        <td width="' . (12 + $wMat) . '%" colspan="2">' . $detail . '</td>
                        <td width="18%" align="right">' . number_format($part->total, 2) . '</td>
                    </tr>';
                }
            }
        }

        $html .= '
                <tr style="font-weight:bold;background-color:#f5f5f5;text-align:center;">
                    <td colspan="' . (3 + $x) . '" style="text-align:right;">Net Quotation Amount</td>
                    <td>' . number_format($totalGrossWeight, 3) . ' g</td>
                    <td></td>
                    <td>' . number_format($invoice->net_amount, 2) . '</td>
                </tr>
            </tbody>
        </table>';

        $pdf->writeHTML($html, true, false, false, false);

        $summaryHtml = '
        <table width="100%" cellpadding="0" border="0" style="margin-top:10px;">
            <tr>
                <td width="45%" valign="top">
                    <table border="1" cellpadding="4" width="100%" style="font-size:9px;">
                        <tr style="background-color:#f5f5f5;"><td><b>Remarks</b></td></tr>
                        <tr><td>' . nl2br(e($invoice->remarks ?: '-')) . '</td></tr>';

        $summaryHtml .= '</table>
                </td>
                <td width="10%"></td>
                <td width="45%" valign="top">
                    <table border="1" cellpadding="4" width="100%" style="font-size:9px;">
                        <tr style="background-color:#f5f5f5;"><td colspan="2" align="center"><b>Summary (' . $invoice->currency . ')</b></td></tr>'
                        . ((float) ($invoice->discount_amount ?? 0) > 0
                            ? '<tr><td width="60%">Invoice Discount' . (($invoice->discount_type ?? '') === 'percent' ? ' (' . rtrim(rtrim(number_format((float) $invoice->discount_value, 4), '0'), '.') . '%)' : '') . '</td><td width="40%" align="right">-' . number_format((float) $invoice->discount_amount, 2) . '</td></tr>'
                            : '')
                        . '
                        <tr style="font-weight:bold;background-color:#eeeeee;">
                            <td width="60%">Invoice Total</td>
                            <td width="40%" align="right">' . number_format($invoice->net_amount, 2) . '</td>
                        </tr>';

        if ($invoice->currency === 'USD') {
            $summaryHtml .= '
                        <tr><td>Exchange Rate</td><td align="right">' . number_format($invoice->exchange_rate, 4) . '</td></tr>
                        <tr><td>Total (AED)</td><td align="right">' . number_format($aedAmount, 2) . '</td></tr>';
        } else {
            $summaryHtml .= '<tr><td>Total (AED)</td><td align="right">' . number_format($aedAmount, 2) . '</td></tr>';
        }

        if (($invoice->vat_amount ?? 0) > 0) {
            $summaryHtml .= '
                        <tr><td>VAT (' . number_format($invoice->vat_percent, 2) . '%)</td>
                            <td align="right">' . number_format($invoice->vat_amount, 2) . '</td></tr>
                        <tr style="font-weight:bold;background-color:#ddeedd;">
                            <td>Grand Total (AED)</td>
                            <td align="right">' . number_format($receiptGrandTotal, 2) . '</td>
                        </tr>';
        }

        $summaryHtml .= '</table></td></tr></table>';

        $pdf->Ln(2);
        $pdf->writeHTML($summaryHtml, true, false, false, false);

        $remainingSpace = $pdf->getPageHeight() - $pdf->GetY() - $pdf->getBreakMargin();
        if ($remainingSpace < 70) { $pdf->AddPage(); }

        $pdf->Ln(2);
        $pdf->SetFont('helvetica', 'B', 9);
        $wordsAED = $pdf->convertCurrencyToWords($receiptGrandTotal, 'AED');
        if ($invoice->currency === 'USD') {
            $wordsUSD = $pdf->convertCurrencyToWords($invoice->net_amount, 'USD');
            $pdf->Cell(0, 5, 'Amount in Words (USD): ' . $wordsUSD, 0, 1, 'L');
            $pdf->Cell(0, 5, 'Amount in Words (AED): ' . $wordsAED, 0, 1, 'L');
        } else {
            $pdf->Cell(0, 5, 'Amount in Words (AED): ' . $wordsAED, 0, 1, 'L');
        }

        $pdf->Ln(2);
        $pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
        $pdf->Ln(2);
        $pdf->SetFont('helvetica', '', 9);
        $termsHtml = '
            <div style="line-height:8px;text-align:justify;color:#333;">
                <b>NOTE:</b> This is a quotation for information and record purposes only. It is not an invoice and does not constitute a sale. Prices are indicative and may change with gold / diamond market rates until the order is confirmed.
            </div>';
        $pdf->writeHTML($termsHtml, true, false, false, false);

        $pdf->Ln(26);
        $y = $pdf->GetY();
        $pdf->Line(20, $y, 80, $y);
        $pdf->Line(130, $y, 190, $y);
        $pdf->SetXY(20, $y);
        $pdf->Cell(50, 5, "Customer's Signature", 0, 0, 'C');
        $pdf->SetXY(130, $y);
        $pdf->Cell(50, 5, "Authorized Signature", 0, 0, 'C');

        return $pdf->Output($invoice->quotation_no . '_simple.pdf', 'I');
    }


    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    /** QUO-00001, QUO-00002 ... one series (taxable or not). */
    private function generateQuotationNo(): string
    {
        $prefix    = 'QUO-';
        $prefixLen = strlen($prefix);

        $maxNo = Quotation::withTrashed()
            ->whereRaw('quotation_no REGEXP ?', ['^' . preg_quote($prefix, '/') . '[0-9]+$'])
            ->lockForUpdate()
            ->selectRaw('MAX(CAST(SUBSTRING(quotation_no, ?) AS UNSIGNED)) as max_no', [$prefixLen + 1])
            ->value('max_no');

        return $prefix . str_pad(((int) $maxNo) + 1, 5, '0', STR_PAD_LEFT);
    }

    private function imageUrlFromPath(?string $imagePath): ?string
    {
        return $imagePath ? asset('storage/app/public/' . $imagePath) : null;
    }

    /**
     * Same per-item maths as SaleInvoiceController::createItems(), minus
     * everything that only exists for accounting / payment (no revenue totals,
     * no material-payment discount rule, no POS flat price).
     */
    private function createItems(Quotation $quotation, array $items, Request $request): void
    {
        $goldRateAedGram = (float) ($request->gold_rate_aed    ?? 0);
        $diamondRateAed  = (float) ($request->diamond_rate_aed ?? 0);

        foreach ($items as $itemData) {
            $grossWeight = (float) ($itemData['gross_weight'] ?? 0);
            $purity      = (float) ($itemData['purity']       ?? 0);
            $makingRate  = (float) ($itemData['making_rate']  ?? 0);
            $vatPercent  = $quotation->is_taxable ? (float) ($itemData['vat_percent'] ?? 0) : 0.0;
            $matType     = $itemData['material_type'] ?? 'gold';

            $purityWeight  = $grossWeight * $purity;
            $col995        = $purityWeight > 0 ? $purityWeight / 0.995 : 0;
            // SELLING PRICE = ITEM TOTAL: a scanned item priced for this
            // quotation's currency posts its selling_price. That price is the
            // item's full value (parts are inside it, no making VAT derived;
            // quotation-level VAT applies on the total).
            $isFlatPrice = isset($itemData['selling_price'])
                && $itemData['selling_price'] !== ''
                && (float) $itemData['selling_price'] > 0;

            if ($isFlatPrice) {
                $makingValue   = 0.0;
                $rate          = 0.0;
                $materialValue = round((float) $itemData['selling_price'], 2);
            } else {
                $makingValue   = $grossWeight * $makingRate;
                $rate          = $matType === 'gold' ? $goldRateAedGram : $diamondRateAed;
                $materialValue = $rate * $purityWeight;
            }

            $partsData  = $itemData['parts'] ?? [];
            $partsTotal = 0.0;

            foreach ($partsData as $partData) {
                $partsTotal += ((float) ($partData['qty'] ?? 0)       * (float) ($partData['rate'] ?? 0))
                             + ((float) ($partData['stone_qty'] ?? 0) * (float) ($partData['stone_rate'] ?? 0));
            }

            if ($isFlatPrice) {
                $partsTotal = 0.0; // already inside the selling price
            }

            // Item-wise discount (flat amount or %). Material / making / parts
            // stay at their undiscounted figures; the discount is stored on its
            // own and subtracted in item_total. VAT is charged on the
            // discounted making.
            $discountBase = $materialValue + $makingValue + $partsTotal;
            [$discType, $discValue, $discAmount] = $this->resolveDiscount(
                $itemData['discount_type']  ?? null,
                $itemData['discount_value'] ?? 0,
                $discountBase
            );
            $discFactor = $discountBase > 0 ? ($discountBase - $discAmount) / $discountBase : 1.0;

            $taxableAmount = $makingValue * $discFactor;
            $vatAmount     = $taxableAmount * ($vatPercent / 100);
            $itemTotal     = $materialValue + $makingValue + $partsTotal - $discAmount + $vatAmount;

            $quotationItem = $quotation->items()->create([
                'item_name'        => $itemData['item_name']        ?? null,
                'product_id'       => $itemData['product_id']       ?? null,
                'item_description' => $itemData['item_description'] ?? null,
                'gross_weight'     => $grossWeight,
                'purity'           => $purity,
                'purity_weight'    => round($purityWeight, 4),
                'col_995'          => round($col995, 4),
                'making_rate'      => $makingRate,
                'making_value'     => round($makingValue, 2),
                'material_type'    => $matType,
                'material_rate'    => $rate,
                'material_value'   => round($materialValue, 2),
                'parts_total'      => round($partsTotal, 2),
                'taxable_amount'   => round($taxableAmount, 2),
                'vat_percent'      => $vatPercent,
                'vat_amount'       => round($vatAmount, 2),
                'item_total'       => round($itemTotal, 2),
                'discount_type'    => $discAmount > 0 ? $discType : null,
                'discount_value'   => $discAmount > 0 ? $discValue : 0,
                'discount_amount'  => $discAmount,
                'barcode_number'   => ($itemData['barcode_number'] ?? null) ?: null,
                'selling_price'    => $isFlatPrice ? round((float) $itemData['selling_price'], 2) : null,
            ]);

            foreach ($partsData as $partData) {
                $qty       = (float) ($partData['qty']        ?? 0);
                $partRate  = (float) ($partData['rate']       ?? 0);
                $stoneQty  = (float) ($partData['stone_qty']  ?? 0);
                $stoneRate = (float) ($partData['stone_rate'] ?? 0);

                $quotationItem->parts()->create([
                    'product_id'       => $partData['product_id']       ?? null,
                    'item_name'        => $partData['item_name']        ?? null,
                    'part_description' => $partData['part_description'] ?? null,
                    'qty'              => $qty,
                    'rate'             => $partRate,
                    'stone_qty'        => $stoneQty,
                    'stone_rate'       => $stoneRate,
                    'total'            => round(($qty * $partRate) + ($stoneQty * $stoneRate), 2),
                ]);
            }
        }
    }

    /**
     * Applies the optional whole-quotation discount on top of the already
     * item-discounted totals, then writes net / VAT / grand total
     * (same order of operations as the sale invoice).
     */
    private function finalizeTotals(Quotation $quotation, Request $request, bool $isTaxable): void
    {
        $netBefore = (float) $quotation->items()->sum('item_total');

        [$type, $value, $amount] = $this->resolveDiscount(
            $request->invoice_discount_type,
            $request->invoice_discount_value,
            $netBefore,
            4   // overall discount keeps up to 4 decimals
        );

        $net    = round($netBefore - $amount, 2);
        $netAed = $request->currency === 'USD'
            ? round($net * ($request->exchange_rate ?? 1), 2)
            : $net;

        $vatPct    = $isTaxable ? (float) ($request->invoice_vat_percent ?? 0) : 0.0;
        $vatAmount = round($netAed * $vatPct / 100, 2);

        $quotation->update([
            'net_amount'      => $net,
            'net_amount_aed'  => $netAed,
            'vat_percent'     => $vatPct,
            'vat_amount'      => $vatAmount,
            'grand_total'     => round($netAed + $vatAmount, 2),
            'discount_type'   => $amount > 0 ? $type : null,
            'discount_value'  => $amount > 0 ? $value : 0,
            'discount_amount' => $amount,
        ]);
    }

    /**
     * (type = amount|percent, value) + base -> [type|null, value, amount].
     * Never negative, never above the base.
     */
    private function resolveDiscount($type, $value, float $base, int $decimals = 2): array
    {
        $value = max(0.0, (float) $value);

        if (!in_array($type, ['amount', 'percent'], true) || $value <= 0 || $base <= 0) {
            return [null, 0.0, 0.0];
        }

        if ($type === 'percent') {
            $value  = min($value, 100.0);
            $amount = $base * $value / 100;
        } else {
            $amount = $value;
        }

        return [$type, $value, round(min($amount, $base), $decimals)];
    }

    private function storeAttachments(Request $request, Quotation $quotation): void
    {
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('quotations', 'public');
                $quotation->attachments()->create(['file_path' => $path]);
            }
        }
    }

    private function validateQuotation(Request $request): void
    {
        $request->validate([
            'is_taxable'               => 'required|boolean',
            'customer_id'              => 'required|exists:chart_of_accounts,id',
            'quotation_date'           => 'required|date',
            'currency'                 => 'required|in:AED,USD',
            'exchange_rate'            => 'nullable|required_if:currency,USD|numeric|min:0',
            'net_amount'               => 'required|numeric|min:0',
            'invoice_vat_percent'      => 'nullable|numeric|min:0|max:100',
            'gold_rate_usd'            => 'nullable|numeric|min:0',
            'gold_rate_aed_ounce'      => 'nullable|numeric|min:0',
            'gold_rate_aed'            => 'nullable|numeric|min:0',
            'diamond_rate_usd'         => 'nullable|numeric|min:0',
            'diamond_rate_aed'         => 'nullable|numeric|min:0',
            'purchase_gold_rate_aed'   => 'nullable|numeric|min:0',
            'purchase_making_rate_aed' => 'nullable|numeric|min:0',
            'items'                    => 'required|array|min:1',
            'items.*.item_name'        => 'nullable|string|required_without:items.*.product_id',
            'items.*.product_id'       => 'nullable|exists:products,id|required_without:items.*.item_name',
            'items.*.gross_weight'     => 'required|numeric|min:0',
            'items.*.purity'           => 'required|numeric|min:0|max:1',
            'items.*.making_rate'      => 'required|numeric|min:0',
            'items.*.material_type'    => 'required|in:gold,diamond',
            'items.*.vat_percent'      => 'required|numeric|min:0',
            'items.*.discount_type'    => 'nullable|in:amount,percent',
            'items.*.discount_value'   => 'nullable|numeric|min:0',
            'invoice_discount_type'    => 'nullable|in:amount,percent',
            'invoice_discount_value'   => 'nullable|numeric|min:0',
        ]);
    }

    /**
     * FEATURE (item code + certificate under the item name in prints).
     * $codes = barcodes to look up; returns barcode => certificate_no.
     */
    private function certificateMap($codes): \Illuminate\Support\Collection
    {
        $codes = collect($codes)->filter()->unique()->values();
        if ($codes->isEmpty()) {
            return collect();
        }
        return PurchaseInvoiceItem::whereIn('barcode_number', $codes)
            ->whereNotNull('certificate_no')
            ->where('certificate_no', '!=', '')
            ->orderBy('id')
            ->pluck('certificate_no', 'barcode_number');
    }

    /** "Code: X | Cert#: Y" caption placed under an item name (empty when neither exists). */
    private function itemCodeHtml(?string $code, ?string $cert): string
    {
        $lines = [];
        if ($code)  $lines[] = 'Code: '  . htmlspecialchars($code);
        if ($cert)  $lines[] = 'Cert#: ' . htmlspecialchars($cert);
        return $lines
            ? '<br><span style="font-size:7px;color:#555555;">' . implode('<br>', $lines) . '</span>'
            : '';
    }
}
