<?php

namespace App\Http\Controllers;

use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Models\Product;
use App\Models\Voucher;
use App\Models\MeasurementUnit;
use App\Models\AccountingEntry;
use App\Models\Purity;
use App\Models\ChartOfAccounts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Services\myPDF;
use Carbon\Carbon;

class PurchaseInvoiceController extends Controller
{
    // =========================================================================
    // INDEX
    // =========================================================================

    public function index()
    {
        $invoices = PurchaseInvoice::with('vendor', 'attachments')->get();
        return view('purchase.index', compact('invoices'));
    }

    // =========================================================================
    // CREATE
    // =========================================================================

    public function create()
    {
        $vendors  = ChartOfAccounts::where('account_type', 'vendor')->get();
        $banks    = ChartOfAccounts::where('account_type', 'bank')->get();
        $products = Product::with('measurementUnit')->get();
        $purities = Purity::all();

        return view('purchase.create', compact('products', 'vendors', 'banks', 'purities'));
    }

    // =========================================================================
    // DOWNLOAD TEMPLATE
    // =========================================================================

    public function downloadTemplate()
    {
        $filename = "purchase_import_template.csv";
        $handle   = fopen('php://output', 'w');

        fputcsv($handle, [
            'Item Name', 'Description', 'Purity', 'Gross Wt',
            'Making Rate', 'Material', 'VAT %',
            'Part Name', 'Part Desc', 'Part Qty', 'Part Rate', 'Stone Qty', 'Stone Rate',
        ]);

        // Sample rows
        fputcsv($handle, ['18K Gold Bracelet','Handmade Chain Design','0.75','12.50','25.00','gold','5','','','','','','']);
        fputcsv($handle, ['','','','','','','','Small Diamonds','VVS1 Round','0.25','1500','10','50']);
        fputcsv($handle, ['22K Wedding Band','Plain Polished','0.92','8.75','15.00','gold','5','','','','','','']);
        fputcsv($handle, ['Diamond Engagement Ring','Solitaire Setting','0.75','4.20','150.00','gold','5','','','','','','']);
        fputcsv($handle, ['','','','','','','','Main Diamond','1.0ct GIA','1.00','8500','0','0']);
        fputcsv($handle, ['','','','','','','','Side Stones','Micro Pave','0.50','1200','24','10']);

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        fclose($handle);
        exit;
    }

    // =========================================================================
    // STORE
    // =========================================================================

    public function store(Request $request)
    {
        $this->clearIrrelevantPaymentFields($request);

        $this->validateInvoice($request);

        try {
            DB::beginTransaction();

            // ── Invoice number ────────────────────────────────────────────────
            $isTaxable  = $request->boolean('is_taxable');
            $prefix     = $isTaxable ? 'PUR-TAX-' : 'PUR-';
            $lastInvoice = PurchaseInvoice::withTrashed()
                ->where('invoice_no', 'LIKE', $prefix . '%')
                ->orderBy('id', 'desc')
                ->first();
            $nextNumber = $lastInvoice ? ((int) str_replace($prefix, '', $lastInvoice->invoice_no)) + 1 : 1;
            $invoiceNo  = $prefix . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);

            // ── Create invoice header (net_amount filled after items loop) ────
            $invoice = PurchaseInvoice::create([
                'invoice_no'           => $invoiceNo,
                'is_taxable'           => $isTaxable,
                'vendor_id'            => $request->vendor_id,
                'invoice_date'         => $request->invoice_date,
                'remarks'              => $request->remarks,
                'currency'             => $request->currency,
                'exchange_rate'        => $request->exchange_rate,
                // Rates stored as submitted from blade ─────────────────────────
                'gold_rate_usd'          => $request->gold_rate_usd,
                'gold_rate_aed_ounce'    => $request->gold_rate_aed_ounce,   // AED/oz (display)
                'gold_rate_aed'          => $request->gold_rate_aed,          // AED/gram ← used in calculations
                'diamond_rate_usd'       => $request->diamond_rate_usd,
                'diamond_rate_aed'       => $request->diamond_rate_aed,          // AED/Ct ← used in calculations
                // ──────────────────────────────────────────────────────────────
                'net_amount'           => 0,
                'net_amount_aed'       => 0,
                'payment_method'       => $request->payment_method,
                'payment_term'         => $request->payment_term,
                'bank_name'            => $request->bank_name,
                'cheque_no'            => $request->cheque_no,
                'cheque_date'          => $request->cheque_date,
                'cheque_amount'        => $request->cheque_amount,
                'transfer_from_bank'   => $request->transfer_from_bank,
                'transfer_to_bank'     => $request->transfer_to_bank,
                'account_title'        => $request->account_title,
                'account_no'           => $request->account_no,
                'transaction_id'       => $request->transaction_id,
                'transfer_date'        => $request->transfer_date,
                'transfer_amount'      => $request->transfer_amount,
                'material_received_by' => $request->material_received_by,
                'material_given_by'    => $request->material_given_by,
                'created_by'           => auth()->id(),
            ]);

            [$totals, $position] = $this->createItems($invoice, $request->items, $request, 1);

            // ── Net amount ─────────────────────────────────────────────────────
            // Formula (matches blade calculateTotals):
            //   Net = totalMaterial + totalDiamondVal + totalStoneVal + totalMaking + totalVAT
            $calculatedNet = $invoice->items()->sum('item_total');

            $calculatedNetAed = $request->currency === 'USD'
                ? round($calculatedNet * ($request->exchange_rate ?? 1), 2)
                : $calculatedNet;

            $invoice->update([
                'net_amount'     => round($calculatedNet, 2),
                'net_amount_aed' => $calculatedNetAed,
            ]);

            // ── Attachments ────────────────────────────────────────────────────
            $this->storeAttachments($request, $invoice);

            // ── Accounting ────────────────────────────────────────────────────
            $this->createPurchaseAccountingEntries($invoice, $totals);

            DB::commit();

            return redirect()
                ->route('purchase_invoices.index')
                ->with('success', 'Invoice #' . $invoiceNo . ' saved successfully.');

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Purchase Invoice Store Error', [
                'message' => $e->getMessage(),
                'line'    => $e->getLine(),
                'file'    => $e->getFile(),
                'trace'   => $e->getTraceAsString(),
            ]);
            return back()->withInput()->with('error', 'Error: ' . $e->getMessage());
        }
    }

    // =========================================================================
    // EDIT
    // =========================================================================

    public function edit($id)
    {
        $purchaseInvoice = PurchaseInvoice::with(['items.parts', 'attachments'])->findOrFail($id);
        $purities        = Purity::all();
        $vendors         = ChartOfAccounts::where('account_type', 'vendor')->get();
        $banks           = ChartOfAccounts::where('account_type', 'bank')->get();
        $products        = Product::with('measurementUnit')->get();

        // Re-derive ounce values for display in the blade header fields
        // gold_rate_aed is stored as AED/gram → convert back to AED/oz for display
        $goldAedOunce    = ($purchaseInvoice->gold_rate_aed    ?? 0) * 31.1035;
        // diamond_rate_aed is stored directly as AED/Ct
        $diamondAedCt = $purchaseInvoice->diamond_rate_aed ?? 0;

        $itemsData = $purchaseInvoice->items->map(function ($item) {
            return [
                'item_name'        => $item->item_name,
                'barcode_number'   => $item->barcode_number,
                'is_printed'       => $item->is_printed,
                'product_id'       => $item->product_id,
                'item_description' => $item->item_description,
                'purity'           => $item->purity,
                // net_weight = user-entered weight (stored in net_weight column)
                'net_weight'       => $item->net_weight,
                // gross_weight = calculated (net_wt + CTS/5)
                'gross_weight'     => $item->gross_weight,
                'making_rate'      => $item->making_rate,
                'material_type'    => $item->material_type,
                'vat_percent'      => $item->vat_percent,
                'purity_weight'    => $item->purity_weight,
                'col_995'          => $item->col_995,
                'making_value'     => $item->making_value,
                'material_value'   => $item->material_value,
                'taxable_amount'   => $item->taxable_amount,
                'vat_amount'       => $item->vat_amount,
                'item_total'       => $item->item_total,
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

        return view('purchase.edit', compact(
            'purchaseInvoice', 'vendors', 'banks', 'products',
            'itemsData', 'goldAedOunce', 'diamondAedCt', 'purities'
        ));
    }

    // =========================================================================
    // UPDATE
    // =========================================================================

    public function update(Request $request, $id)
    {
        $invoice = PurchaseInvoice::findOrFail($id);

        // ── Printed-item guard ────────────────────────────────────────────────
        $incomingBarcodes  = collect($request->items)->pluck('barcode_number')->filter()->values();
        $printedAndDeleted = $invoice->items()
            ->where('is_printed', true)
            ->whereNotIn('barcode_number', $incomingBarcodes->toArray())
            ->pluck('barcode_number');

        if ($printedAndDeleted->isNotEmpty() && !$request->boolean('confirm_delete_printed')) {
            return back()
                ->withInput()
                ->with('printed_delete_warning', $printedAndDeleted->join(', '));
        }

        $this->clearIrrelevantPaymentFields($request);
        $this->validateInvoice($request);

        try {
            DB::beginTransaction();

            // ── Update invoice header ─────────────────────────────────────────
            $invoice->update([
                'is_taxable'           => $request->boolean('is_taxable'),
                'vendor_id'            => $request->vendor_id,
                'invoice_date'         => $request->invoice_date,
                'remarks'              => $request->remarks,
                'currency'             => $request->currency,
                'exchange_rate'        => $request->exchange_rate,
                'gold_rate_usd'          => $request->gold_rate_usd,
                'gold_rate_aed_ounce'    => $request->gold_rate_aed_ounce,
                'gold_rate_aed'          => $request->gold_rate_aed,
                'diamond_rate_usd'       => $request->diamond_rate_usd,
                'diamond_rate_aed'       => $request->diamond_rate_aed,
                'net_amount'           => $invoice->net_amount,      // temp, overwritten below
                'net_amount_aed'       => $invoice->net_amount_aed,
                'payment_method'       => $request->payment_method,
                'payment_term'         => $request->payment_term,
                'bank_name'            => $request->bank_name,
                'cheque_no'            => $request->cheque_no,
                'cheque_date'          => $request->cheque_date,
                'cheque_amount'        => $request->cheque_amount,
                'transfer_from_bank'   => $request->transfer_from_bank,
                'transfer_to_bank'     => $request->transfer_to_bank,
                'account_title'        => $request->account_title,
                'account_no'           => $request->account_no,
                'transaction_id'       => $request->transaction_id,
                'transfer_date'        => $request->transfer_date,
                'transfer_amount'      => $request->transfer_amount,
                'material_received_by' => $request->material_received_by,
                'material_given_by'    => $request->material_given_by,
            ]);

            // ── Delete old items + parts ───────────────────────────────────────
            foreach ($invoice->items as $oldItem) {
                $oldItem->parts()->delete();
            }
            $invoice->items()->delete();

            // ── Re-create items (preserve printed status by barcode) ──────────
            [$totals] = $this->createItems($invoice, $request->items, $request, 1, preservePrinted: true);

            // ── Recalculate net amount ─────────────────────────────────────────
            $calculatedNet = $invoice->items()->sum('item_total');

            $calculatedNetAed = $request->currency === 'USD'
                ? round($calculatedNet * ($request->exchange_rate ?? 1), 2)
                : $calculatedNet;

            $invoice->update([
                'net_amount'     => round($calculatedNet, 2),
                'net_amount_aed' => $calculatedNetAed,
            ]);

            // ── Attachments ────────────────────────────────────────────────────
            $this->storeAttachments($request, $invoice);

            // ── Reverse old voucher + recreate ────────────────────────────────
            $oldVoucher = Voucher::where('reference_type', PurchaseInvoice::class)
                ->where('reference_id', $invoice->id)
                ->first();
            if ($oldVoucher) {
                AccountingEntry::where('voucher_id', $oldVoucher->id)->delete();
                $oldVoucher->delete();
            }

            $this->createPurchaseAccountingEntries($invoice, $totals);

            DB::commit();

            return redirect()
                ->route('purchase_invoices.index')
                ->with('success', 'Invoice #' . $invoice->invoice_no . ' updated successfully.');

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Purchase Invoice Update Error', [
                'message' => $e->getMessage(),
                'line'    => $e->getLine(),
                'file'    => $e->getFile(),
                'trace'   => $e->getTraceAsString(),
            ]);
            return back()->withInput()->with('error', 'Error: ' . $e->getMessage());
        }
    }

    // =========================================================================
    // PRINT
    // =========================================================================

    public function print($id)
    {
        $invoice = PurchaseInvoice::with([
            'vendor',
            'items',
            'items.product.measurementUnit',
            'items.parts',
            'items.parts.product.measurementUnit',
            'items.parts.variation.attributeValues.attribute',
            'bank',
            'transferBank',
            'vouchers.entries.account',
        ])->findOrFail($id);

        // Totals from stored values
        $totalMaterialAed = $invoice->items->sum('material_value');
        $totalMakingAed   = $invoice->items->sum('making_value');   // MC only (no parts)
        $totalVatAed      = $invoice->items->sum('vat_amount');     // VAT on MC only
        $totalTaxableAed  = $invoice->items->sum('taxable_amount'); // = making only (MC)

        // Diamond + stone part values (summing from parts directly)
        $totalDiamondVal = $invoice->items->sum(function ($item) {
            return $item->parts->sum(fn($p) => $p->qty * $p->rate);
        });
        $totalStoneVal = $invoice->items->sum(function ($item) {
            return $item->parts->sum(fn($p) => ($p->stone_qty ?? 0) * ($p->stone_rate ?? 0));
        });
        $totalPartsAed = $invoice->items->sum(function ($item) {
            return $item->parts->sum('total');
        });

        // Currency payable = MC + Parts + VAT (what vendor gets paid in cash)
        $totalCurrencyPayable = $totalMakingAed + $totalPartsAed + $totalVatAed;

        $pdf = new myPDF();
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetCreator('Your App');
        $pdf->SetTitle($invoice->invoice_no);
        $pdf->SetMargins(10, 10, 10);
        $pdf->setCellPadding(1.2);

        // ── PAGE 1: PURCHASE INVOICE ──────────────────────────────────────────
        $pdf->AddPage();

        $logoPath = public_path('assets/img/mj-logo.jpeg');
        $logoHtml = file_exists($logoPath) ? '<img src="' . $logoPath . '" width="85">' : '';
        $pdf->writeHTML('
            <table width="100%" cellpadding="3">
                <tr>
                    <td width="40%">' . $logoHtml . '</td>
                    <td width="60%" style="text-align:right;font-size:10px;">
                        <strong>MUSFIRA JEWELRY L.L.C</strong><br>
                        Suite #M04, Mezzanine floor, Al Buteen 2 Building, Gold Souq. Gate no.1, Deira, Dubai<br>
                        TRN No: 104902647700003
                    </td>
                </tr>
            </table><hr>', true, false, false, false);

        $title = $invoice->is_taxable ? 'TAX INVOICE (PURCHASE)' : 'PURCHASE INVOICE';
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 6, $title, 0, 1, 'C');
        $pdf->Ln(2);
        $pdf->SetFont('helvetica', '', 9);

        $goldRateDisplay    = $invoice->currency === 'USD' ? $invoice->gold_rate_usd    : $invoice->gold_rate_aed_ounce;
        $diamondRateDisplay = $invoice->currency === 'USD' ? $invoice->diamond_rate_usd : $invoice->diamond_rate_aed;

        $vendorHtml = '
        <table cellpadding="3" width="100%">
            <tr>
                <td width="50%">
                    <b>To:</b><br>
                    ' . ($invoice->vendor->name ?? '-') . '<br>
                    ' . ($invoice->vendor->address ?? '-') . '<br>
                    Contact: ' . ($invoice->vendor->contact_no ?? '-') . '<br>
                    TRN: ' . ($invoice->vendor->trn ?? '-') . '<br>
                </td>
                <td width="50%">
                    <table border="1" cellpadding="3" width="100%">
                        <tr><td width="45%"><b>Date</b></td><td width="55%">' . Carbon::parse($invoice->invoice_date)->format('d.m.Y') . '</td></tr>
                        <tr><td><b>Invoice No</b></td><td>' . $invoice->invoice_no . '</td></tr>
                        <tr><td><b>Gold Rate (' . $invoice->currency . '/oz)</b></td><td>' . number_format($goldRateDisplay, 2) . '</td></tr>
                        <tr><td><b>Gold Rate (AED/g)</b></td><td>' . number_format($invoice->gold_rate_aed, 4) . '</td></tr>
                        <tr><td><b>Diamond Rate (' . $invoice->currency . '/Ct)</b></td><td>' . number_format($diamondRateDisplay, 2) . '</td></tr>
                    </table>
                </td>
            </tr>
        </table>';
        $pdf->writeHTML($vendorHtml, true, false, false, false);

        // ── Items table ───────────────────────────────────────────────────────
        $html = '
        <table border="1" cellpadding="3" width="100%" style="font-size:8px;">
            <thead>
                <tr style="font-weight:bold;background-color:#f5f5f5;text-align:center;">
                    <th width="3%"  rowspan="2">#</th>
                    <th width="10%" rowspan="2">Item Name</th>
                    <th width="10%" rowspan="2">Description</th>
                    <th width="6%"  rowspan="2">Net Wt</th>
                    <th width="7%"  rowspan="2">Gold Gross Wt</th>
                    <th width="6%"  rowspan="2">Purity</th>
                    <th width="6%"  rowspan="2">Purity Wt</th>
                    <th width="6%"  rowspan="2">995</th>
                    <th width="13%" colspan="2">Making</th>
                    <th width="7%"  rowspan="2">Material</th>
                    <th width="8%"  rowspan="2">Material Val</th>
                    <th width="6%"  rowspan="2">MC</th>
                    <th width="5%"  rowspan="2">VAT%</th>
                    <th width="7%"  rowspan="2">Item Total</th>
                </tr>
                <tr style="font-weight:bold;background-color:#f5f5f5;text-align:center;">
                    <th width="6%">Rate</th>
                    <th width="7%">Value</th>
                </tr>
            </thead>
            <tbody>';

        foreach ($invoice->items as $index => $item) {
            $hasParts     = $item->parts && $item->parts->count() > 0;
            $productTotal = $item->item_total; // material + making + parts + vat

            $html .= '
                <tr style="text-align:center;background-color:#ffffff;">
                    <td width="3%">' . ($index + 1) . '</td>
                    <td width="10%">' . ($item->item_name ?: ($item->product->name ?? '-')) . '</td>
                    <td width="10%">' . ($item->item_description ?? '-') . '</td>
                    <td width="6%">' . number_format($item->net_weight, 3) . '</td>
                    <td width="7%">' . number_format($item->gross_weight, 3) . '</td>
                    <td width="6%">' . number_format($item->purity, 3) . '</td>
                    <td width="6%">' . number_format($item->purity_weight, 3) . '</td>
                    <td width="6%">' . number_format($item->col_995 ?? 0, 3) . '</td>
                    <td width="6%">' . number_format($item->making_rate ?? 0, 2) . '</td>
                    <td width="7%">' . number_format($item->making_value, 2) . '</td>
                    <td width="7%">' . ucfirst($item->material_type) . '</td>
                    <td width="8%">' . number_format($item->material_value, 2) . '</td>
                    <td width="6%">' . number_format($item->taxable_amount, 2) . '</td>
                    <td width="5%">' . number_format($item->vat_percent, 0) . '%</td>
                    <td width="7%" style="font-weight:bold;">' . number_format($item->item_total, 2) . '</td>
                </tr>';

            if ($hasParts) {
                $html .= '<tr style="background-color:#f9f9f9;font-style:italic;font-size:7px;">
                            <td></td><td colspan="14"><b>Parts Detail:</b></td>
                        </tr>';

                foreach ($item->parts as $part) {
                    $displayPartName = $part->item_name ?: ($part->product->name ?? 'Part');
                    $html .= '
                    <tr style="font-size:7.5px;background-color:#fcfcfc;">
                        <td></td>
                        <td colspan="2" style="text-align:left;">' . $displayPartName . '</td>
                        <td colspan="2" style="text-align:left;">' . htmlspecialchars($part->part_description ?? '') . '</td>
                        <td colspan="2" style="text-align:center;">' . $part->qty . ' Ct.</td>
                        <td colspan="2" style="text-align:center;">Rate: ' . number_format($part->rate, 2) . '</td>
                        <td colspan="2" style="text-align:center;">St.Qty: ' . number_format($part->stone_qty ?? 0, 0) . '</td>
                        <td colspan="2" style="text-align:center;">St.Rate: ' . number_format($part->stone_rate ?? 0, 2) . '</td>
                        <td colspan="2" style="text-align:right;font-weight:bold;">' . number_format($part->total, 2) . '</td>
                    </tr>';
                }

                // Grand total row = item_total (material + making + parts + vat)
                $html .= '
                    <tr style="background-color:#eeeeee;font-weight:bold;font-size:8px;">
                        <td colspan="13" align="right">Product Grand Total (Material + MC + Parts + VAT):</td>
                        <td colspan="2" align="right">' . number_format($productTotal, 2) . '</td>
                    </tr>';
            }
        }

        $html .= '
                <tr style="font-weight:bold;background-color:#f5f5f5;">
                    <td colspan="13" align="right">Net Invoice Amount</td>
                    <td colspan="2" align="right">' . number_format($invoice->net_amount, 2) . '</td>
                </tr>
            </tbody>
        </table>';

        $pdf->writeHTML($html, true, false, false, false);

        // ── SUMMARY ────────────────────────────────────────────────────────────
        $aedAmount = $invoice->currency === 'USD' ? $invoice->net_amount_aed : $invoice->net_amount;

        $summaryHtml = '
        <table width="100%" cellpadding="0" border="0" style="margin-top:10px;">
            <tr>
                <td width="45%" valign="top">
                    <table border="1" cellpadding="4" width="100%" style="font-size:9px;">
                        <tr style="background-color:#f5f5f5;"><td><b>Payment Details</b></td><td><b>Value</b></td></tr>
                        <tr><td>Method</td><td>' . ucfirst($invoice->payment_method) . '</td></tr>';

        if ($invoice->payment_method === 'credit') {
            $summaryHtml .= '<tr><td>Payment Term</td><td>' . ($invoice->payment_term ?? '-') . '</td></tr>';
        }
        if ($invoice->payment_method === 'cheque') {
            $summaryHtml .= '
            <tr><td>Bank Name</td><td>'   . ($invoice->bank->name ?? '-') . '</td></tr>
            <tr><td>Cheque No</td><td>'   . ($invoice->cheque_no ?? '-') . '</td></tr>
            <tr><td>Cheque Date</td><td>' . ($invoice->cheque_date ? Carbon::parse($invoice->cheque_date)->format('d.m.Y') : '-') . '</td></tr>';
        }
        if ($invoice->payment_method === 'bank_transfer') {
            $summaryHtml .= '
            <tr><td>From Bank</td><td>'       . ($invoice->transferBank->name ?? '-') . '</td></tr>
            <tr><td>Vendor Bank</td><td>'     . ($invoice->transfer_to_bank ?? '-') . '</td></tr>
            <tr><td>Account Title</td><td>'   . ($invoice->account_title ?? '-') . '</td></tr>
            <tr><td>Account No</td><td>'      . ($invoice->account_no ?? '-') . '</td></tr>
            <tr><td>Transfer Date</td><td>'   . ($invoice->transfer_date ? Carbon::parse($invoice->transfer_date)->format('d.m.Y') : '-') . '</td></tr>
            <tr><td>Transaction Ref</td><td>' . ($invoice->transaction_id ?? '-') . '</td></tr>
            <tr><td>Transfer Amount</td><td>' . number_format($invoice->transfer_amount ?? 0, 2) . '</td></tr>';
        }
        if (str_contains($invoice->payment_method, 'material')) {
            $totalPureWeight = $invoice->items->sum('purity_weight');
            $summaryHtml .= '
            <tr><td>Material Given By</td><td>'    . ($invoice->material_given_by ?? '-') . '</td></tr>
            <tr><td>Material Received By</td><td>' . ($invoice->material_received_by ?? '-') . '</td></tr>
            <tr><td>Total Pure Weight</td><td>'    . number_format($totalPureWeight, 3) . ' gms</td></tr>
            <tr><td>Making Charges</td><td>'       . number_format($totalMakingAed, 2) . ' AED</td></tr>';
        }

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
                        <tr><td>Total VAT (on MC)</td>                       <td align="right">' . number_format($totalVatAed, 2) . '</td></tr>
                        <tr style="font-weight:bold;background-color:#ddeeee;">
                            <td>Currency Payable (MC + Parts + VAT)</td>
                            <td align="right">' . number_format($totalCurrencyPayable, 2) . '</td>
                        </tr>
                        <tr style="font-weight:bold;background-color:#eeeeee;">
                            <td>Invoice Total</td>
                            <td align="right">' . number_format($invoice->net_amount, 2) . '</td>
                        </tr>';

        if ($invoice->currency === 'USD') {
            $summaryHtml .= '
                        <tr><td>Exchange Rate</td><td align="right">' . number_format($invoice->exchange_rate, 4) . '</td></tr>
                        <tr style="font-weight:bold;"><td>Total (AED)</td><td align="right">' . number_format($aedAmount, 2) . '</td></tr>';
        } else {
            $summaryHtml .= '<tr style="font-weight:bold;"><td>Total (AED)</td><td align="right">' . number_format($aedAmount, 2) . '</td></tr>';
        }

        $summaryHtml .= '</table></td></tr></table>';

        $pdf->Ln(2);
        $pdf->writeHTML($summaryHtml, true, false, false, false);

        // ── Check remaining space before Terms & Conditions + Signatures ─────
        $remainingSpace = $pdf->getPageHeight() - $pdf->GetY() - $pdf->getBreakMargin();
        if ($remainingSpace < 70) {
            $pdf->AddPage();
        }

        // Amount in words
        $pdf->Ln(2);
        $pdf->SetFont('helvetica', 'B', 9);
        $wordsAED = $pdf->convertCurrencyToWords($aedAmount, 'AED');
        if ($invoice->currency === 'USD') {
            $wordsUSD = $pdf->convertCurrencyToWords($invoice->net_amount, 'USD');
            $pdf->Cell(0, 5, 'Amount in Words (USD): ' . $wordsUSD, 0, 1, 'L');
            $pdf->Cell(0, 5, 'Amount in Words (AED): ' . $wordsAED, 0, 1, 'L');
        } else {
            $pdf->Cell(0, 5, 'Amount in Words (AED): ' . $wordsAED, 0, 1, 'L');
        }

        // Terms & Conditions
        $pdf->Ln(2);
        $pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
        $pdf->Ln(2);
        $pdf->SetFont('helvetica', '', 9);
        $termsHtml = '
            <div style="line-height:8px;text-align:justify;color:#333;">
                <b>TERMS & CONDITIONS:</b> Goods sold on credit, if not paid when due, or in case of law suit arising there from,
                the purchaser agrees to pay the seller all expense of recovery, collection, etc., including attorney fees,
                legal expense and/or recovery-agent charges. <b>GOODS ONCE SOLD CANNOT BE RETURNED OR EXCHANGED.</b>
                Any dispute arising out of or in connection with this sale shall be subject to the exclusive jurisdiction of Dubai Courts.
            </div>';
        $pdf->writeHTML($termsHtml, true, false, false, false);

        // Signatures
        $pdf->Ln(26);
        $y = $pdf->GetY();
        $pdf->Line(20, $y, 80, $y);
        $pdf->Line(130, $y, 190, $y);
        $pdf->SetXY(20, $y);
        $pdf->Cell(50, 5, "Receiver's Signature", 0, 0, 'C');
        $pdf->SetXY(130, $y);
        $pdf->Cell(50, 5, "Authorized Signature", 0, 0, 'C');

        // ── Material fixing pages ─────────────────────────────────────────────
        if (str_contains(strtolower($invoice->payment_method), 'material')) {
            $pdf->AddPage();
            $this->renderMetalFixingPage($pdf, $invoice, $totalMaterialAed, 'PARTY COPY');
            $pdf->AddPage();
            $this->renderMetalFixingPage($pdf, $invoice, $totalMaterialAed, 'ACCOUNTS COPY');
        }

        // ── Currency payment pages (MC + Parts + VAT) ─────────────────────────
        $pdf->AddPage();
        $this->renderCurrencyPaymentPage($pdf, $invoice, $totalMakingAed, $totalPartsAed, $totalVatAed, 'PARTY COPY');
        $pdf->AddPage();
        $this->renderCurrencyPaymentPage($pdf, $invoice, $totalMakingAed, $totalPartsAed, $totalVatAed, 'ACCOUNTS COPY');

        return $pdf->Output($invoice->invoice_no . '.pdf', 'I');
    }

    // =========================================================================
    // PRINT BARCODES
    // =========================================================================

    public function printBarcodes($id)
    {
        $invoice = PurchaseInvoice::with('items')->findOrFail($id);
        $invoice->items()->update(['is_printed' => true]);
        return view('purchase.barcodes', compact('invoice'));
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    /**
     * Create item rows and their parts for a given invoice.
     *
     * Formulas (must match blade calculateRow exactly):
     *   purity_weight  = net_weight × purity
     *   col_995        = purity_weight / 0.995
     *   making_value   = net_weight × making_rate          (Net Wt, NOT gross)
     *   material_value = rate × purity_weight
     *   partsTotal     = Σ (qty×rate + stone_qty×stone_rate) per part
     *   taxable        = making_value + partsTotal
     *   vat_amount     = taxable × (vat_percent / 100)
     *   item_total     = material_value + taxable + vat_amount
     *
     * net_amount (invoice level, matches blade calculateTotals):
     *   = Σ material_value + Σ(diamond qty×rate) + Σ(stone qty×stone_rate) + Σ making + Σ vat
     *
     * @return array  [ $totals, $position ]
     */
    private function createItems(PurchaseInvoice $invoice,array $items,Request $request,int $startPosition = 1,bool $preservePrinted = false): array {
        $totals = [
            'gold_material'    => 0.0,
            'diamond_material' => 0.0,
            'material'         => 0.0,
            'making'           => 0.0,
            'diamond_val'      => 0.0,  // Σ (part qty × part rate)
            'stone_val'        => 0.0,  // Σ (stone qty × stone rate)
            'vat'              => 0.0,
        ];

        $position = $startPosition;

        $goldRateAedGram  = (float) ($request->gold_rate_aed    ?? 0);
        $diamondRateAedCt = (float) ($request->diamond_rate_aed ?? 0);

        foreach ($items as $itemData) {

            $netWeight   = (float) ($itemData['net_weight']   ?? 0);
            $grossWeight = (float) ($itemData['gross_weight'] ?? 0);
            $purity      = (float) ($itemData['purity']       ?? 0);
            $makingRate  = (float) ($itemData['making_rate']  ?? 0);
            $vatPercent  = (float) ($itemData['vat_percent']  ?? 0);
            $matType     = $itemData['material_type'] ?? 'gold';

            // ── Row calculations ──────────────────────────────────────────────────
            $purityWeight  = $netWeight * $purity;
            $col995        = $purityWeight > 0 ? $purityWeight / 0.995 : 0;
            $makingValue   = $netWeight * $makingRate;

            $rate          = $matType === 'gold' ? $goldRateAedGram : $diamondRateAedCt;
            $materialValue = $rate * $purityWeight;

            // ── Parts ─────────────────────────────────────────────────────────────
            $partsData      = $itemData['parts'] ?? [];
            $partsTotal     = 0.0;
            $itemDiamondVal = 0.0;
            $itemStoneVal   = 0.0;

            foreach ($partsData as $partData) {
                $qty       = (float) ($partData['qty']        ?? 0);
                $partRate  = (float) ($partData['rate']       ?? 0);
                $stoneQty  = (float) ($partData['stone_qty']  ?? 0);
                $stoneRate = (float) ($partData['stone_rate'] ?? 0);

                $diaValue   = $qty      * $partRate;
                $stoneValue = $stoneQty * $stoneRate;
                $partTotal  = $diaValue + $stoneValue;

                $partsTotal     += $partTotal;
                $itemDiamondVal += $diaValue;
                $itemStoneVal   += $stoneValue;
            }

            // ── Taxable = making only (VAT is on MC, not parts) ───────────────────
            $taxableAmount = $makingValue;
            $vatAmount     = $taxableAmount * ($vatPercent / 100);
            $itemTotal     = $materialValue + $makingValue + $partsTotal + $vatAmount;

            // ── Barcode / printed status ──────────────────────────────────────────
            $existingBarcode   = $itemData['barcode_number'] ?? null;
            $wasAlreadyPrinted = false;
            if ($preservePrinted && $existingBarcode) {
                $wasAlreadyPrinted = PurchaseInvoiceItem::where('barcode_number', $existingBarcode)
                    ->value('is_printed') ?? false;
            }

            $invoiceItem = $invoice->items()->create([
                'item_name'        => $itemData['item_name']        ?? null,
                'product_id'       => $itemData['product_id']       ?? null,
                'item_description' => $itemData['item_description'] ?? null,
                'net_weight'       => $netWeight,
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
                'taxable_amount'   => round($taxableAmount, 2),  // making only
                'vat_percent'      => $vatPercent,
                'vat_amount'       => round($vatAmount, 2),
                'item_total'       => round($itemTotal, 2),      // material + making + parts + vat
                'barcode_number'   => $existingBarcode ?? $this->generateBarcodeNumber($invoice, $position),
                'is_printed'       => $wasAlreadyPrinted,
            ]);

            // ── Create parts ──────────────────────────────────────────────────────
            foreach ($partsData as $partData) {
                $qty       = (float) ($partData['qty']        ?? 0);
                $partRate  = (float) ($partData['rate']       ?? 0);
                $stoneQty  = (float) ($partData['stone_qty']  ?? 0);
                $stoneRate = (float) ($partData['stone_rate'] ?? 0);
                $partTotal = ($qty * $partRate) + ($stoneQty * $stoneRate);

                $invoiceItem->parts()->create([
                    'product_id'       => $partData['product_id']       ?? null,
                    'item_name'        => $partData['item_name']        ?? null,
                    'part_description' => $partData['part_description'] ?? null,
                    'qty'              => $qty,
                    'rate'             => $partRate,
                    'stone_qty'        => $stoneQty,
                    'stone_rate'       => $stoneRate,
                    'total'            => round($partTotal, 2),
                ]);
            }

            // ── Accumulate totals ─────────────────────────────────────────────────
            if ($matType === 'gold') {
                $totals['gold_material'] += $materialValue;
            } else {
                $totals['diamond_material'] += $materialValue;
            }
            $totals['material']    += $materialValue;
            $totals['making']      += $makingValue;
            $totals['diamond_val'] += $itemDiamondVal;
            $totals['stone_val']   += $itemStoneVal;
            $totals['vat']         += $vatAmount;

            $position++;
        }

        return [$totals, $position];
    }

    /**
     * Store file attachments linked to an invoice.
     */
    private function storeAttachments(Request $request, PurchaseInvoice $invoice): void
    {
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('purchase_invoices', 'public');
                $invoice->attachments()->create(['file_path' => $path]);
            }
        }
    }

    /**
     * Null out payment fields that don't belong to the selected payment method.
     */
    private function clearIrrelevantPaymentFields(Request $request): void
    {
        if ($request->payment_method !== 'cheque') {
            $request->merge([
                'bank_name'     => null,
                'cheque_no'     => null,
                'cheque_date'   => null,
                'cheque_amount' => null,
            ]);
        }

        if ($request->payment_method !== 'bank_transfer') {
            $request->merge([
                'transfer_from_bank' => null,
                'transfer_to_bank'   => null,
                'account_title'      => null,
                'account_no'         => null,
                'transaction_id'     => null,
                'transfer_date'      => null,
                'transfer_amount'    => null,
            ]);
        }
    }

    /**
     * Validate the invoice request.
     * Field names match exactly what the blade POSTs.
     */
    private function validateInvoice(Request $request): void
    {
        $request->validate([
            'is_taxable'             => 'required|boolean',
            'vendor_id'              => 'required|exists:chart_of_accounts,id',
            'invoice_date'           => 'required|date',
            'currency'               => 'required|in:AED,USD',
            'exchange_rate'          => 'nullable|required_if:currency,USD|numeric|min:0',
            'net_amount'             => 'required|numeric|min:0',
            'payment_method'         => 'required|in:credit,cash,cheque,bank_transfer,material+making cost',
            'payment_term'           => 'nullable|string',
            // Gold rates
            'gold_rate_usd'          => 'nullable|numeric|min:0',
            'gold_rate_aed_ounce'    => 'nullable|numeric|min:0',   // AED/oz (display only)
            'gold_rate_aed'          => 'nullable|numeric|min:0',   // AED/gram (used in calc)
            // Diamond rates
            'diamond_rate_usd'       => 'nullable|numeric|min:0',
            'diamond_rate_aed'       => 'nullable|numeric|min:0',   // AED/Ct (used in calculations)
            // Cheque fields
            'bank_name'              => 'nullable|required_if:payment_method,cheque|exists:chart_of_accounts,id',
            'cheque_no'              => 'nullable|required_if:payment_method,cheque|string',
            'cheque_date'            => 'nullable|required_if:payment_method,cheque|date',
            'cheque_amount'          => 'nullable|required_if:payment_method,cheque|numeric|min:0',
            // Bank transfer fields
            'transfer_from_bank'     => 'nullable|required_if:payment_method,bank_transfer|exists:chart_of_accounts,id',
            'transfer_to_bank'       => 'nullable|string',
            'account_title'          => 'nullable|string',
            'account_no'             => 'nullable|string',
            'transaction_id'         => 'nullable|string',
            'transfer_date'          => 'nullable|required_if:payment_method,bank_transfer|date',
            'transfer_amount'        => 'nullable|required_if:payment_method,bank_transfer|numeric|min:0',
            // Items
            'items'                  => 'required|array|min:1',
            'items.*.item_name'      => 'nullable|string|required_without:items.*.product_id',
            'items.*.product_id'     => 'nullable|exists:products,id|required_without:items.*.item_name',
            'items.*.net_weight'     => 'required|numeric|min:0',
            'items.*.gross_weight'   => 'required|numeric|min:0',
            'items.*.purity'         => 'required|numeric|min:0|max:1',
            'items.*.making_rate'    => 'required|numeric|min:0',
            'items.*.material_type'  => 'required|in:gold,diamond',
            'items.*.vat_percent'    => 'required|numeric|min:0',
            // Material + making cost
            'material_given_by'      => 'nullable|required_if:payment_method,material+making cost|string',
            'material_received_by'   => 'nullable|required_if:payment_method,material+making cost|string',
        ]);
    }

    /**
     * Create accounting voucher + double-entry lines for a purchase invoice.
     *
     * DEBIT:
     *   510001 — Gold Material Purchases
     *   510002 — Diamond Material Purchases
     *   510003 — Making Charges Expense
     *   105001 — VAT Input Tax Recoverable
     *   (Parts are NOT journalized — informational only)
     *
     * CREDIT:
     *   Vendor / Cash / Bank — depending on payment method
     *
     * Note: net_amount on the invoice = material + diamond_val + stone_val + making + vat
     *       BUT accounting only journals material + making + vat (parts are pass-through).
     *       The journal debit total = material + making + vat.
     */

    protected function createPurchaseAccountingEntries(PurchaseInvoice $invoice, array $totals): Voucher
    {
        $acct = function (string $code) use ($invoice): int {
            $account = ChartOfAccounts::where('account_code', $code)->first();
            if (!$account) {
                throw new \Exception(
                    "Account code [{$code}] not found in Chart of Accounts (Invoice #{$invoice->invoice_no})."
                );
            }
            return $account->id;
        };

        $voucher = Voucher::create([
            'voucher_no'     => Voucher::generateVoucherNo('purchase'),
            'voucher_type'   => 'purchase',
            'voucher_date'   => $invoice->invoice_date,
            'reference_type' => PurchaseInvoice::class,
            'reference_id'   => $invoice->id,
            'ac_dr_sid'      => null,
            'ac_cr_sid'      => null,
            'amount'         => null,
            'remarks'        => 'Purchase Invoice #' . $invoice->invoice_no,
            'created_by'     => auth()->id(),
        ]);

        $entries = [];

        // ── DEBIT entries ─────────────────────────────────────────────────────

        if ($totals['gold_material'] > 0) {
            $entries[] = [
                'voucher_id' => $voucher->id,
                'account_id' => $acct('510001'),
                'debit'      => round($totals['gold_material'], 2),
                'credit'     => 0,
                'narration'  => 'Gold material purchase — Inv# ' . $invoice->invoice_no,
            ];
        }

        if ($totals['diamond_material'] > 0) {
            $entries[] = [
                'voucher_id' => $voucher->id,
                'account_id' => $acct('510002'),
                'debit'      => round($totals['diamond_material'], 2),
                'credit'     => 0,
                'narration'  => 'Diamond material purchase — Inv# ' . $invoice->invoice_no,
            ];
        }

        // ── Diamond & stone parts purchase (510004) ───────────────────────────
        $partsTotal = round($totals['diamond_val'] + $totals['stone_val'], 2);
        if ($partsTotal > 0) {
            $entries[] = [
                'voucher_id' => $voucher->id,
                'account_id' => $acct('510004'),
                'debit'      => $partsTotal,
                'credit'     => 0,
                'narration'  => 'Diamond/stone parts purchase — Inv# ' . $invoice->invoice_no,
            ];
        }

        if ($totals['making'] > 0) {
            $entries[] = [
                'voucher_id' => $voucher->id,
                'account_id' => $acct('510003'),
                'debit'      => round($totals['making'], 2),
                'credit'     => 0,
                'narration'  => 'Making charges — Inv# ' . $invoice->invoice_no,
            ];
        }

        if ($totals['vat'] > 0) {
            $entries[] = [
                'voucher_id' => $voucher->id,
                'account_id' => $acct('105001'),
                'debit'      => round($totals['vat'], 2),
                'credit'     => 0,
                'narration'  => 'Input VAT recoverable — Inv# ' . $invoice->invoice_no,
            ];
        }

        $totalDebit = round(collect($entries)->sum('debit'), 2);

        if ($totalDebit <= 0) {
            throw new \Exception(
                "Invoice #{$invoice->invoice_no} has zero accounting value — no entries created."
            );
        }

        // ── CREDIT entry ──────────────────────────────────────────────────────
        switch ($invoice->payment_method) {
            case 'credit':
                $entries[] = [
                    'voucher_id' => $voucher->id,
                    'account_id' => $invoice->vendor_id,
                    'debit'      => 0,
                    'credit'     => $totalDebit,
                    'narration'  => 'Purchase on credit — payable to vendor',
                ];
                break;

            case 'cash':
                $entries[] = [
                    'voucher_id' => $voucher->id,
                    'account_id' => $acct('101001'),
                    'debit'      => 0,
                    'credit'     => $totalDebit,
                    'narration'  => 'Cash paid for purchase — Inv# ' . $invoice->invoice_no,
                ];
                break;

            case 'cheque':
                if (!$invoice->bank_name) {
                    throw new \Exception('Bank account required for cheque payment (Inv# ' . $invoice->invoice_no . ').');
                }
                $entries[] = [
                    'voucher_id' => $voucher->id,
                    'account_id' => $invoice->bank_name,
                    'debit'      => 0,
                    'credit'     => $totalDebit,
                    'narration'  => 'Cheque #' . $invoice->cheque_no . ' — Inv# ' . $invoice->invoice_no,
                ];
                break;

            case 'bank_transfer':
                if (!$invoice->transfer_from_bank) {
                    throw new \Exception('Transfer-from bank required for bank transfer (Inv# ' . $invoice->invoice_no . ').');
                }
                $entries[] = [
                    'voucher_id' => $voucher->id,
                    'account_id' => $invoice->transfer_from_bank,
                    'debit'      => 0,
                    'credit'     => $totalDebit,
                    'narration'  => 'Bank transfer Ref# ' . $invoice->transaction_id . ' — Inv# ' . $invoice->invoice_no,
                ];
                break;

            case 'material+making cost':
                $entries[] = [
                    'voucher_id' => $voucher->id,
                    'account_id' => $invoice->vendor_id,
                    'debit'      => 0,
                    'credit'     => $totalDebit,
                    'narration'  => 'Material + making cost — payable to ' . ($invoice->material_given_by ?? 'vendor'),
                ];
                break;

            default:
                throw new \Exception('Unrecognised payment method: "' . $invoice->payment_method . '"');
        }

        foreach ($entries as $entry) {
            AccountingEntry::create($entry);
        }

        // ── Balance check ─────────────────────────────────────────────────────
        $sumDebits  = round(collect($entries)->sum('debit'),  2);
        $sumCredits = round(collect($entries)->sum('credit'), 2);

        if ($sumDebits !== $sumCredits) {
            throw new \Exception(
                "Accounting imbalance on Invoice #{$invoice->invoice_no}: " .
                "Debits {$sumDebits} ≠ Credits {$sumCredits}."
            );
        }

        Log::info('Purchase accounting entries created', [
            'invoice_no'       => $invoice->invoice_no,
            'voucher_no'       => $voucher->voucher_no,
            'gold_material'    => $totals['gold_material'],
            'diamond_material' => $totals['diamond_material'],
            'diamond_val'      => $totals['diamond_val'],
            'stone_val'        => $totals['stone_val'],
            'parts_total'      => $partsTotal,
            'making'           => $totals['making'],
            'vat_input'        => $totals['vat'],
            'total_debit'      => $sumDebits,
            'total_credit'     => $sumCredits,
        ]);

        return $voucher;
    }

    // ── Barcode ───────────────────────────────────────────────────────────────

    private function generateBarcodeNumber(PurchaseInvoice $invoice, int $itemPosition): string
    {
        $prefix    = $invoice->is_taxable ? 'MJT-' : 'MJ-';
        $invoiceNo = substr($invoice->invoice_no, strrpos($invoice->invoice_no, '-') + 1);
        return $prefix . $invoiceNo . '-' . $itemPosition;
    }

    // ── PDF helpers ───────────────────────────────────────────────────────────

    private function renderMetalFixingPage($pdf, $invoice, $totalMaterialVal, string $copyType = 'PARTY COPY'): void
    {
        $logoPath = public_path('assets/img/mj-logo.jpeg');
        $logoHtml = file_exists($logoPath) ? '<img src="' . $logoPath . '" width="80">' : '';

        $pdf->writeHTML('
        <table width="100%" cellpadding="2"><tr>
            <td width="30%">' . $logoHtml . '</td>
            <td width="70%" style="text-align:right;font-size:9px;">
                <strong style="font-size:12px;">MUSFIRA JEWELRY L.L.C</strong><br>
                M04 Al Buteen 2 Building, Old Baldiya Street, Gold Souq Gate 1 Dubai UAE.<br>
                TRN: 104902647700003
            </td>
        </tr></table><hr>', true, false, false, false);

        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(120, 8, 'METAL SALE FIXING', 0, 0, 'R');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(70, 8, strtoupper($copyType), 0, 1, 'R');
        $pdf->Ln(5);

        $pdf->writeHTML('
        <table width="100%" cellpadding="0"><tr>
            <td width="60%">
                <b>To:</b><br>' . ($invoice->vendor->name ?? '-') . '<br>' . ($invoice->vendor->address ?? '-') . '<br>
                Contact: ' . ($invoice->vendor->contact_no ?? '-') . '<br>TRN: ' . ($invoice->vendor->trn ?? '-') . '
            </td>
            <td width="40%">
                <table border="1" cellpadding="3" width="100%">
                    <tr><td><b>Invoice #</b></td><td><b>' . $invoice->invoice_no . '</b></td></tr>
                    <tr><td><b>Date</b></td><td><b>' . Carbon::parse($invoice->invoice_date)->format('d/m/Y') . '</b></td></tr>
                </table>
            </td>
        </tr></table>', true, false, false, false);

        $totalPureWt = $invoice->items->sum('purity_weight');
        $rate        = $invoice->currency === 'USD' ? $invoice->gold_rate_usd : $invoice->gold_rate_aed_ounce;
        $rateUnit    = $invoice->currency === 'USD' ? 'GOZ' : 'GMS';
        $materialAED = $invoice->currency === 'USD'
            ? round($totalMaterialVal * $invoice->exchange_rate, 2)
            : $totalMaterialVal;

        $pdf->Ln(2);
        $pdf->writeHTML('<b>WE HAVE</b>', true, false, false, false);
        $pdf->writeHTML('
        <table width="100%" style="border:1px solid #000;"><tr><td>
            BOUGHT FINE GOLD <b>' . number_format($totalPureWt, 3) . ' (GMS) @ ' . number_format($rate, 2) . ' / ' . $rateUnit . '</b><br>
            EQUIVALENT MATERIAL VALUE ..... <b>AED ' . number_format($materialAED, 2) . '</b>
        </td></tr></table>', true, false, false, false);

        $words = $pdf->convertCurrencyToWords($materialAED, 'AED');
        $pdf->writeHTML('
        <table width="100%" cellpadding="4" style="border:1px solid #000;">
            <tr>
                <td width="30%" style="border:1px solid #000;background-color:#f0f0f0;">Your account has been updated with:</td>
                <td width="70%" rowspan="2" valign="middle">' . strtoupper($words) . '</td>
            </tr>
            <tr><td style="border-right:0.5px solid #000;"><b>DEBITED AED ' . number_format($materialAED, 2) . '</b></td></tr>
        </table>', true, false, false, false);

        $pdf->Ln(1);
        $pdf->SetFont('helvetica', '', 8);
        $pdf->writeHTML(
            'Being ' . number_format($totalPureWt, 2) . ' gms pure gold rate ' . number_format($rate, 2) .
            ' /- fixed with ' . ($invoice->vendor->name ?? '-') . ' TRN-' . ($invoice->vendor->trn ?? '-') .
            ' Against Purchase Invoice # ' . $invoice->invoice_no . '.',
            true, false, false, false
        );

        $pdf->Ln(20);
        $y = $pdf->GetY();
        $pdf->SetFont('helvetica', '', 7);
        $pdf->Line(10, $y, 55, $y);   $pdf->SetXY(10, $y + 1); $pdf->Cell(45, 5, "SUPPLIER'S SIGNATURE", 0, 0, 'C');
        $pdf->Line(65, $y, 95, $y);   $pdf->SetXY(65, $y + 1); $pdf->Cell(30, 5, 'For Accounts', 0, 0, 'C');
        $pdf->Line(110, $y, 140, $y); $pdf->SetXY(110, $y + 1); $pdf->Cell(30, 5, 'Checked By', 0, 0, 'C');
        $pdf->SetXY(150, $y - 9); $pdf->SetFont('helvetica', 'B', 7);
        $pdf->Cell(50, 3, 'For MUSFIRA JEWELRY L L C', 0, 0, 'C');
        $pdf->Line(155, $y, 195, $y); $pdf->SetXY(155, $y + 1);
        $pdf->SetFont('helvetica', '', 7); $pdf->Cell(40, 5, 'AUTHORISED SIGNATORY', 0, 0, 'C');
    }

    private function renderCurrencyPaymentPage($pdf, $invoice, float $totalMaking, float $totalParts, float $totalVat, string $copyType = 'PARTY COPY'): void
    {
        $logoPath = public_path('assets/img/mj-logo.jpeg');
        $logoHtml = file_exists($logoPath) ? '<img src="' . $logoPath . '" width="80">' : '';

        $pdf->writeHTML('
        <table width="100%" cellpadding="2"><tr>
            <td width="30%">' . $logoHtml . '</td>
            <td width="70%" style="text-align:right;font-size:9px;">
                <strong style="font-size:12px;">MUSFIRA JEWELRY L.L.C</strong><br>
                M04 Al Buteen 2 Building, Old Baldiya Street, Gold Souq Gate 1 Dubai UAE.<br>
                TRN: 104902647700003
            </td>
        </tr></table><hr>', true, false, false, false);

        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(120, 8, 'CURRENCY PAYMENT', 0, 0, 'R');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(70, 8, strtoupper($copyType), 0, 1, 'R');
        $pdf->Ln(2);

        $ex        = (float) ($invoice->exchange_rate ?? 1);
        $makingAED = $invoice->currency === 'USD' ? round($totalMaking * $ex, 2) : $totalMaking;
        $partsAED  = $invoice->currency === 'USD' ? round($totalParts  * $ex, 2) : $totalParts;
        $vatAED    = $invoice->currency === 'USD' ? round($totalVat    * $ex, 2) : $totalVat;
        $payable   = $makingAED + $partsAED + $vatAED;

        $pdf->writeHTML('
        <table width="100%" cellpadding="0"><tr>
            <td width="60%">
                <b>To:</b><br>' . ($invoice->vendor->name ?? '-') . '<br>' . ($invoice->vendor->address ?? '-') . '<br>
                Contact: ' . ($invoice->vendor->contact_no ?? '-') . '<br>TRN: ' . ($invoice->vendor->trn ?? '-') . '
            </td>
            <td width="40%">
                <table border="1" cellpadding="3" width="100%">
                    <tr><td><b>REF DOC. NO#</b></td><td><b>' . $invoice->invoice_no . '</b></td></tr>
                    <tr><td><b>DATE</b></td><td><b>' . Carbon::parse($invoice->invoice_date)->format('d/m/Y') . '</b></td></tr>
                </table>
            </td>
        </tr></table>', true, false, false, false);

        $pdf->Ln(2);
        $pdf->SetFont('helvetica', '', 8);
        $pdf->writeHTML('
        <table width="100%" cellpadding="5" border="1" style="border-collapse:collapse;">
            <tr style="background-color:#f2f2f2;font-weight:bold;text-align:center;">
                <th width="10%">No.</th>
                <th width="50%">Account Description</th>
                <th width="20%">Amount (AED)</th>
                <th width="20%">Total (AED)</th>
            </tr>
            <tr style="text-align:center;">
                <td>1</td>
                <td align="left">
                    <b>Labour, Making Charges & Parts</b><br>
                    Making: ' . number_format($makingAED, 2) . 
                    ' | Parts: ' . number_format($partsAED, 2) . 
                    ' | VAT: ' . number_format($vatAED, 2) . '<br>
                    Against Purchase Invoice # ' . $invoice->invoice_no. '
                </td>
                <td>' . number_format($payable, 2) . '</td>
                <td>' . number_format($payable, 2) . '</td>
            </tr>
            <tr style="font-weight:bold;background-color:#f9f9f9;">
                <td colspan="2" align="right">Total Payment Amount</td>
                <td align="center">' . number_format($payable, 2) . '</td>
                <td align="center">' . number_format($payable, 2) . '</td>
            </tr>
        </table>', true, false, false, false);

        $words = $pdf->convertCurrencyToWords($payable, 'AED');
        $pdf->Ln(2);
        $pdf->writeHTML('
        <table width="100%" cellpadding="4" style="border:1px solid #000;">
            <tr>
                <td width="30%" style="border:1px solid #000;background-color:#f2f2f2;">Account Update:</td>
                <td width="70%">' . strtoupper($words) . '</td>
            </tr>
            <tr>
                <td style="border-right:0.5px solid #000;"><b>AED ' . number_format($payable, 2) . ' DEBITED</b></td>
                <td>Being payment for service charges, parts and tax.</td>
            </tr>
        </table>', true, false, false, false);

        $pdf->Ln(30);
        $y = $pdf->GetY();
        $pdf->SetFont('helvetica', '', 7);
        $pdf->Line(10, $y, 55, $y);   $pdf->SetXY(10, $y + 1); $pdf->Cell(45, 5, "RECEIVER'S SIGNATURE", 0, 0, 'C');
        $pdf->Line(65, $y, 95, $y);   $pdf->SetXY(65, $y + 1); $pdf->Cell(30, 5, 'Prepared By', 0, 0, 'C');
        $pdf->Line(110, $y, 140, $y); $pdf->SetXY(110, $y + 1); $pdf->Cell(30, 5, 'Checked By', 0, 0, 'C');
        $pdf->SetXY(150, $y - 9); $pdf->SetFont('helvetica', 'B', 7);
        $pdf->Cell(50, 3, 'For MUSFIRA JEWELRY L L C', 0, 0, 'C');
        $pdf->Line(155, $y, 195, $y); $pdf->SetXY(155, $y + 1);
        $pdf->SetFont('helvetica', '', 7); $pdf->Cell(40, 5, 'AUTHORISED SIGNATORY', 0, 0, 'C');
    }
}