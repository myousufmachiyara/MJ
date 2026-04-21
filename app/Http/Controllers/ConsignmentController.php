<?php

namespace App\Http\Controllers;

use App\Models\Consignment;
use App\Models\ConsignmentItem;
use App\Models\ConsignmentItemPart;
use App\Models\ChartOfAccounts;
use App\Models\SaleInvoice;
use App\Models\SaleInvoiceItem;
use App\Models\PurchaseInvoiceItem;
use App\Models\Purity;
use App\Models\Product;
use App\Services\myPDF;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ConsignmentController extends Controller
{
    // =========================================================================
    // INDEX
    // =========================================================================

    public function index()
    {
        $consignments = Consignment::with('partner')
            ->withCount(['items', 'items as sold_count' => fn($q) => $q->where('item_status','sold')])
            ->orderByDesc('id')
            ->get();

        return view('consignments.index', compact('consignments'));
    }

    // =========================================================================
    // CREATE
    // =========================================================================

    public function create()
    {
        $partners = ChartOfAccounts::whereIn('account_type', ['customer', 'vendor'])->orderBy('name')->get();
        $purities = Purity::orderBy('value')->get();
        $products = Product::orderBy('name')->get();

        return view('consignments.create', compact('partners', 'purities', 'products'));
    }

    // =========================================================================
    // STORE
    // =========================================================================

    public function store(Request $request)
    {
        $this->validateConsignment($request);

        try {
            DB::beginTransaction();

            $consignment = Consignment::create([
                'consignment_no' => Consignment::generateNo($request->direction),
                'direction'      => $request->direction,
                'partner_id'     => $request->partner_id,
                'start_date'     => $request->start_date,
                'end_date'       => $request->end_date ?: null,
                'duration_label' => $request->duration_label,
                'status'         => 'active',
                'remarks'        => $request->remarks,
                'created_by'     => auth()->id(),
            ]);

            $this->persistItems($consignment, $request->items ?? [], $consignment->direction, $request);

            DB::commit();

            return redirect()
                ->route('consignments.show', $consignment->id)
                ->with('success', 'Consignment ' . $consignment->consignment_no . ' created.');

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('ConsignmentController::store — ' . $e->getMessage(), [
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return back()->withInput()->with('error', 'Error saving consignment: ' . $e->getMessage());
        }
    }

    // =========================================================================
    // SHOW
    // =========================================================================

    public function show($id)
    {
        $consignment = Consignment::with(['partner', 'items.parts', 'createdBy'])->findOrFail($id);

        return view('consignments.show', compact('consignment'));
    }

    // =========================================================================
    // EDIT
    // =========================================================================

    public function edit($id)
    {
        $consignment = Consignment::with(['items.parts'])->findOrFail($id);

        if (!in_array($consignment->status, ['active', 'partially_settled'])) {
            return redirect()->route('consignments.show', $id)
                ->with('error', 'Only active or partially settled consignments can be edited.');
        }

        $partners = ChartOfAccounts::whereIn('account_type', ['customer', 'vendor'])->orderBy('name')->get();
        $purities = Purity::orderBy('value')->get();
        $products = Product::orderBy('name')->get();

        // Pass existing items as JSON for the JS item builder
        $itemsJson = $consignment->items->map(function ($item) {
            return [
                'id'               => $item->id,
                'item_name'        => $item->item_name,
                'item_description' => $item->item_description,
                'barcode_number'   => $item->barcode_number,
                'is_printed'       => $item->is_printed,
                'item_status'      => $item->item_status,
                'gross_weight'     => $item->gross_weight,
                'purity'           => $item->purity,
                'purity_weight'    => $item->purity_weight,
                'col_995'          => $item->col_995,
                'making_rate'      => $item->making_rate,
                'making_value'     => $item->making_value,
                'material_type'    => $item->material_type,
                'material_rate'    => $item->material_rate,
                'material_value'   => $item->material_value,
                'taxable_amount'   => $item->taxable_amount,
                'vat_percent'      => $item->vat_percent,
                'vat_amount'       => $item->vat_amount,
                'agreed_value'     => $item->agreed_value,
                'parts'            => $item->parts->map(fn($p) => [
                    'item_name'        => $p->item_name,
                    'part_description' => $p->part_description,
                    'qty'              => $p->qty,
                    'rate'             => $p->rate,
                    'stone_qty'        => $p->stone_qty,
                    'stone_rate'       => $p->stone_rate,
                    'total'            => $p->total,
                ])->values(),
            ];
        })->values()->toJson();

        return view('consignments.edit', compact('consignment', 'partners', 'purities', 'products', 'itemsJson'));
    }

    // =========================================================================
    // UPDATE
    // =========================================================================

    public function update(Request $request, $id)
    {
        $consignment = Consignment::findOrFail($id);
        $this->validateConsignment($request, updating: true);

        try {
            DB::beginTransaction();

            $consignment->update([
                'partner_id'     => $request->partner_id,
                'start_date'     => $request->start_date,
                'end_date'       => $request->end_date ?: null,
                'duration_label' => $request->duration_label,
                'remarks'        => $request->remarks,
            ]);

            // Delete only in_stock items (sold/returned items are immutable)
            foreach ($consignment->items()->where('item_status', 'in_stock')->get() as $item) {
                $item->parts()->delete();
                $item->delete();
            }

            // Re-create the items that came in from the form (only in_stock rows)
            $incomingItems = collect($request->items ?? [])->where('item_status', '!=', 'sold')
                                                           ->where('item_status', '!=', 'returned')
                                                           ->values()->toArray();

            $this->persistItems($consignment, $incomingItems, $consignment->direction, $request);

            $consignment->recalcStatus();

            DB::commit();

            return redirect()->route('consignments.show', $consignment->id)
                ->with('success', 'Consignment updated.');

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('ConsignmentController::update — ' . $e->getMessage(), [
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return back()->withInput()->with('error', 'Error updating: ' . $e->getMessage());
        }
    }

    // =========================================================================
    // DESTROY
    // =========================================================================

    public function destroy($id)
    {
        $consignment = Consignment::findOrFail($id);

        if ($consignment->items()->where('item_status', 'sold')->exists()) {
            return back()->with('error', 'Cannot delete — some items are already settled against a sale invoice.');
        }

        $consignment->delete();

        return redirect()->route('consignments.index')->with('success', 'Consignment deleted.');
    }

    // =========================================================================
    // MARK ITEM AS RETURNED
    // =========================================================================

    public function returnItem($consignmentId, $itemId)
    {
        $item = ConsignmentItem::where('consignment_id', $consignmentId)
            ->where('id', $itemId)
            ->where('item_status', 'in_stock')
            ->firstOrFail();

        $item->update(['item_status' => 'returned']);
        $item->consignment->recalcStatus();

        return back()->with('success', 'Item marked as returned.');
    }

    // =========================================================================
    // PRINT BARCODES  (inbound only — same as purchase module)
    // Barcode format: CSG-XXXXX-1  (CSG prefix distinguishes from MJ/MJT barcodes)
    // =========================================================================

    public function printBarcodes($id)
    {
        $consignment = Consignment::with('items')->findOrFail($id);

        if ($consignment->direction !== 'inbound') {
            return back()->with('error', 'Barcodes are only for inbound consignments.');
        }

        $items = $consignment->items->whereNotNull('barcode_number')->values();
        if ($items->isEmpty()) {
            return back()->with('error', 'No barcodes found.');
        }

        $pdf = new myPDF();
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetTitle('Barcodes — ' . $consignment->consignment_no);
        $pdf->SetMargins(5, 5, 5);
        $pdf->setCellPadding(1);
        $pdf->AddPage();
        $pdf->SetFont('helvetica', '', 7);

        $cols       = 4;
        $cellW      = 47;
        $cellH      = 30;
        $col        = 0;

        foreach ($items as $item) {
            if ($col === $cols) {
                $pdf->Ln($cellH);
                $col = 0;
            }

            $x = $pdf->GetX();
            $y = $pdf->GetY();
            $pdf->Rect($x, $y, $cellW, $cellH);

            // Barcode number line
            $pdf->SetXY($x + 1, $y + 1);
            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->Cell($cellW - 2, 4, $item->barcode_number, 0, 1, 'C');

            // Item name
            $pdf->SetXY($x + 1, $y + 6);
            $pdf->SetFont('helvetica', '', 6);
            $pdf->Cell($cellW - 2, 3, mb_substr($item->item_name ?: 'N/A', 0, 28), 0, 1, 'C');

            // Weight + purity
            $pdf->SetXY($x + 1, $y + 10);
            $pdf->Cell($cellW - 2, 3,
                'GW: ' . number_format($item->gross_weight, 3) . 'g  |  Purity: ' . $item->purity,
                0, 1, 'C');

            // Agreed value
            $pdf->SetXY($x + 1, $y + 14);
            $pdf->Cell($cellW - 2, 3, 'AED ' . number_format($item->agreed_value, 2), 0, 1, 'C');

            // 1D Barcode graphic
            $pdf->write1DBarcode(
                $item->barcode_number, 'C128',
                $x + 2, $y + 18,
                $cellW - 4, 8,
                0.4,
                ['fgcolor' => [0,0,0], 'bgcolor' => false, 'text' => false, 'padding' => 0]
            );

            $item->update(['is_printed' => true]);

            $pdf->SetXY($x + $cellW, $y);
            $col++;
        }

        return $pdf->Output($consignment->consignment_no . '_barcodes.pdf', 'I');
    }

    // =========================================================================
    // SCAN BARCODE — Ajax endpoint
    // Called by SaleInvoiceController::scanBarcode when CSG- prefix detected.
    // Returns same payload shape as the purchase barcode scan.
    // =========================================================================

    public function scanBarcode(Request $request)
    {
        $barcode = trim($request->get('barcode', ''));

        if (!$barcode) {
            return response()->json(['success' => false, 'message' => 'No barcode provided.'], 422);
        }

        $item = ConsignmentItem::with(['parts', 'consignment'])
            ->where('barcode_number', $barcode)
            ->where('item_status', 'in_stock')
            ->first();

        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Consignment barcode "' . $barcode . '" not found or already sold/returned.',
            ], 404);
        }

        return response()->json([
            'success'          => true,
            'source'           => 'consignment',
            'consignment_no'   => $item->consignment->consignment_no,
            'barcode_number'   => $item->barcode_number,
            'item_name'        => $item->item_name,
            'item_description' => $item->item_description,
            'purity'           => $item->purity,
            'gross_weight'     => $item->gross_weight,
            'making_rate'      => $item->making_rate,
            'material_type'    => $item->material_type,
            'vat_percent'      => $item->vat_percent,
            'agreed_value'     => $item->agreed_value,
            'parts'            => $item->parts->map(fn($p) => [
                'item_name'        => $p->item_name,
                'part_description' => $p->part_description,
                'qty'              => $p->qty,
                'rate'             => $p->rate,
                'stone_qty'        => $p->stone_qty,
                'stone_rate'       => $p->stone_rate,
                'total'            => $p->total,
            ])->values(),
        ]);
    }

    // =========================================================================
    // SETTLE ITEMS — called from SaleInvoiceController after store/update
    // Marks inbound CSG- barcodes as sold and links them to the sale invoice.
    // Usage in SaleInvoiceController:
    //   \App\Http\Controllers\ConsignmentController::settleItems($invoice);
    // =========================================================================

    public static function settleItems(SaleInvoice $invoice): void
    {
        try {
            foreach ($invoice->items as $saleItem) {
                if (!$saleItem->barcode_number) continue;
                // Only process CSG- barcodes
                if (!str_starts_with($saleItem->barcode_number, 'CSG-')) continue;

                $consignmentItem = ConsignmentItem::where('barcode_number', $saleItem->barcode_number)
                    ->where('item_status', 'in_stock')
                    ->first();

                if (!$consignmentItem) continue;

                $consignmentItem->update([
                    'item_status'                 => 'sold',
                    'settled_by_sale_invoice_id'  => $invoice->id,
                    'settled_date'                => $invoice->invoice_date,
                ]);

                $consignmentItem->consignment->recalcStatus();
            }
        } catch (\Throwable $e) {
            Log::error('ConsignmentController::settleItems — ' . $e->getMessage());
        }
    }

    // =========================================================================
    // PRINT — delivery/receipt PDF document
    // =========================================================================

    public function print($id)
    {
        $consignment = Consignment::with(['partner', 'items.parts', 'createdBy'])->findOrFail($id);

        $pdf = new myPDF();
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetTitle($consignment->consignment_no);
        $pdf->SetMargins(10, 10, 10);
        $pdf->setCellPadding(1.5);
        $pdf->AddPage();

        $logoPath = public_path('assets/img/mj-logo.jpeg');
        $logoHtml = file_exists($logoPath) ? '<img src="' . $logoPath . '" width="85">' : '';

        // Header
        $pdf->writeHTML('
        <table width="100%" cellpadding="3">
          <tr>
            <td width="40%">' . $logoHtml . '</td>
            <td width="60%" style="text-align:right;font-size:10px;">
              <strong>MUSFIRA JEWELRY L.L.C</strong><br>
              Suite #M04, Mezzanine floor, Al Buteen 2 Building,<br>
              Gold Souq. Gate no.1, Deira, Dubai<br>
              TRN No: 104902647700003
            </td>
          </tr>
        </table><hr>', true, false, false, false);

        $docTitle = $consignment->direction === 'inbound'
            ? 'CONSIGNMENT RECEIPT NOTE (Inbound)'
            : 'CONSIGNMENT DELIVERY NOTE (Outbound)';

        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 7, $docTitle, 0, 1, 'C');
        $pdf->Ln(2);

        // Meta
        $direction   = $consignment->direction === 'inbound' ? 'Received From' : 'Delivered To';
        $pdf->writeHTML('
        <table cellpadding="3" width="100%">
          <tr>
            <td width="50%" valign="top">
              <strong>' . $direction . ':</strong><br>
              ' . e($consignment->partner->name ?? '-') . '<br>
              ' . e($consignment->partner->address ?? '') . '<br>
              Tel: ' . e($consignment->partner->contact_no ?? '-') . '
            </td>
            <td width="50%">
              <table border="1" cellpadding="3" width="100%" style="font-size:9px;">
                <tr><td width="45%"><strong>Consignment No</strong></td><td>' . $consignment->consignment_no . '</td></tr>
                <tr><td><strong>Start Date</strong></td><td>' . $consignment->start_date->format('d-M-Y') . '</td></tr>
                <tr><td><strong>End Date</strong></td><td>' . ($consignment->end_date ? $consignment->end_date->format('d-M-Y') : 'Open-ended') . '</td></tr>
                <tr><td><strong>Duration</strong></td><td>' . ($consignment->duration_label ?: '-') . '</td></tr>
                <tr><td><strong>Status</strong></td><td>' . ucwords(str_replace('_', ' ', $consignment->status)) . '</td></tr>
              </table>
            </td>
          </tr>
        </table>', true, false, false, false);
        $pdf->Ln(3);

        // Items
        $html  = '<table border="1" cellpadding="3" width="100%" style="font-size:8px;">';
        $html .= '<thead><tr style="background-color:#f0f0f0;font-weight:bold;text-align:center;">
            <th>#</th><th>Barcode</th><th>Item</th><th>Type</th>
            <th>Purity</th><th>Gross Wt</th><th>Purity Wt</th>
            <th>Making Val</th><th>Material Val</th><th>Agreed Val</th><th>Status</th>
          </tr></thead><tbody>';

        $totGross = $totMaking = $totMaterial = $totAgreed = 0;

        foreach ($consignment->items as $i => $item) {
            $bg = match($item->item_status) {
                'sold'     => '#d4edda',
                'returned' => '#fff3cd',
                default    => '#ffffff',
            };
            $html .= '<tr style="text-align:center;background-color:' . $bg . ';">
                <td>' . ($i + 1) . '</td>
                <td style="font-size:7px;">' . ($item->barcode_number ?: '-') . '</td>
                <td style="text-align:left;">' . e($item->item_name ?: '-') . '</td>
                <td>' . ucfirst($item->material_type) . '</td>
                <td>' . number_format($item->purity, 3) . '</td>
                <td>' . number_format($item->gross_weight, 3) . '</td>
                <td>' . number_format($item->purity_weight, 3) . '</td>
                <td>' . number_format($item->making_value, 2) . '</td>
                <td>' . number_format($item->material_value, 2) . '</td>
                <td style="font-weight:bold;">' . number_format($item->agreed_value, 2) . '</td>
                <td>' . ucfirst($item->item_status) . '</td>
              </tr>';

            $totGross    += $item->gross_weight;
            $totMaking   += $item->making_value;
            $totMaterial += $item->material_value;
            $totAgreed   += $item->agreed_value;
        }

        $html .= '<tr style="font-weight:bold;background-color:#f0f0f0;text-align:center;">
            <td colspan="5">TOTAL</td>
            <td>' . number_format($totGross, 3) . '</td>
            <td></td>
            <td>' . number_format($totMaking, 2) . '</td>
            <td>' . number_format($totMaterial, 2) . '</td>
            <td>' . number_format($totAgreed, 2) . '</td>
            <td></td>
          </tr></tbody></table>';

        $pdf->writeHTML($html, true, false, false, false);

        if ($consignment->remarks) {
            $pdf->Ln(3);
            $pdf->SetFont('helvetica', '', 8);
            $pdf->MultiCell(0, 4, 'Remarks: ' . $consignment->remarks, 0, 'L');
        }

        // Signatures
        $pdf->Ln(20);
        $y = $pdf->GetY();
        $pdf->Line(20, $y, 85, $y);
        $pdf->Line(125, $y, 190, $y);
        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetXY(20,  $y + 1); $pdf->Cell(65, 5, 'Partner Signature', 0, 0, 'C');
        $pdf->SetXY(125, $y + 1); $pdf->Cell(65, 5, 'Authorized Signature', 0, 0, 'C');

        return $pdf->Output($consignment->consignment_no . '.pdf', 'I');
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    private function validateConsignment(Request $request, bool $updating = false): void
    {
        $rules = [
            'partner_id'          => 'required|exists:chart_of_accounts,id',
            'start_date'          => 'required|date',
            'end_date'            => 'nullable|date|after_or_equal:start_date',
            'duration_label'      => 'nullable|string|max:60',
            'remarks'             => 'nullable|string',
            'items'               => 'required|array|min:1',
            'items.*.item_name'   => 'nullable|string|max:255',
            'items.*.gross_weight'=> 'required|numeric|min:0',
            'items.*.purity'      => 'required|numeric|min:0|max:1',
            'items.*.making_rate' => 'required|numeric|min:0',
            'items.*.material_type' => 'required|in:gold,diamond',
            'items.*.agreed_value'  => 'required|numeric|min:0',
        ];

        if (!$updating) {
            $rules['direction'] = 'required|in:inbound,outbound';
        }

        $request->validate($rules);
    }

    /**
     * Create ConsignmentItem + ConsignmentItemPart records.
     * For inbound items: generates a CSG-XXXXX-1 barcode after creation.
     * Uses identical formulas to sale/purchase modules.
     */
    private function persistItems(Consignment $consignment, array $items, string $direction, Request $request): void
    {
        $goldRateAed = (float) ($request->gold_rate_aed     ?? 0);
        $diaRateAed  = (float) ($request->diamond_rate_aed  ?? 0);

        foreach ($items as $itemData) {
            $grossWeight = (float) ($itemData['gross_weight']  ?? 0);
            $purity      = (float) ($itemData['purity']        ?? 0);
            $makingRate  = (float) ($itemData['making_rate']   ?? 0);
            $vatPercent  = (float) ($itemData['vat_percent']   ?? 0);
            $matType     = $itemData['material_type']           ?? 'gold';
            $agreedValue = (float) ($itemData['agreed_value']  ?? 0);
            $itemGoldR   = (float) ($itemData['gold_rate_aed']    ?? $goldRateAed);
            $itemDiaR    = (float) ($itemData['diamond_rate_aed'] ?? $diaRateAed);

            // Formulas match purchase/sale modules exactly
            $purityWeight  = $grossWeight * $purity;
            $col995        = $purityWeight > 0 ? $purityWeight / 0.995 : 0;
            $makingValue   = $grossWeight * $makingRate;
            $rate          = $matType === 'gold' ? $itemGoldR : $itemDiaR;
            $materialValue = $rate * $purityWeight;

            // Parts (diamonds / stones)
            $partsData  = $itemData['parts'] ?? [];
            $partsTotal = 0.0;
            foreach ($partsData as $p) {
                $partsTotal += ((float)($p['qty'] ?? 0) * (float)($p['rate'] ?? 0))
                             + ((float)($p['stone_qty'] ?? 0) * (float)($p['stone_rate'] ?? 0));
            }

            $taxableAmount = $makingValue;
            $vatAmount     = $taxableAmount * ($vatPercent / 100);

            // If agreed value was not entered, compute from components
            if ($agreedValue == 0) {
                $agreedValue = $materialValue + $makingValue + $partsTotal + $vatAmount;
            }

            $item = $consignment->items()->create([
                'item_name'        => $itemData['item_name']        ?? null,
                'product_id'       => $itemData['product_id']       ?? null,
                'item_description' => $itemData['item_description'] ?? null,
                'barcode_number'   => null, // generated below for inbound
                'is_printed'       => false,
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
                'agreed_value'     => round($agreedValue, 2),
                'item_status'      => 'in_stock',
            ]);

            // Generate barcode ONLY for inbound (we receive the goods, we tag them)
            // Format: CSG-XXXXX-1  — scannable by the sale invoice scanner
            if ($direction === 'inbound') {
                $item->update(['barcode_number' => 'CSG-' . str_pad($item->id, 5, '0', STR_PAD_LEFT) . '-1']);
            }

            // Parts
            foreach ($partsData as $p) {
                $qty       = (float) ($p['qty']        ?? 0);
                $pRate     = (float) ($p['rate']       ?? 0);
                $stoneQty  = (float) ($p['stone_qty']  ?? 0);
                $stoneRate = (float) ($p['stone_rate'] ?? 0);
                $item->parts()->create([
                    'item_name'        => $p['item_name']        ?? null,
                    'part_description' => $p['part_description'] ?? null,
                    'qty'              => $qty,
                    'rate'             => $pRate,
                    'stone_qty'        => $stoneQty,
                    'stone_rate'       => $stoneRate,
                    'total'            => round(($qty * $pRate) + ($stoneQty * $stoneRate), 2),
                ]);
            }
        }
    }
}