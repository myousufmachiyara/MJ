<?php

namespace App\Http\Controllers;

use App\Models\Consignment;
use App\Models\ConsignmentItem;
use App\Models\ConsignmentItemPart;
use App\Models\ChartOfAccounts;
use App\Models\SaleInvoice;
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
            ->withCount([
                'items',
                'items as sold_count'     => fn($q) => $q->where('item_status', 'sold'),
                'items as returned_count' => fn($q) => $q->where('item_status', 'returned'),
            ])
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
        $products = Product::with('measurementUnit')->orderBy('name')->get();

        $itemsJson = '[]';
        $isEdit    = false;

        return view('consignments.form', compact('partners', 'purities', 'products', 'itemsJson', 'isEdit'));
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
                ->route('consignments.index')
                ->with('success', 'Consignment ' . $consignment->consignment_no . ' created.');

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Consignment::store — ' . $e->getMessage(), [
                'file' => $e->getFile(), 'line' => $e->getLine(),
            ]);
            return back()->withInput()->with('error', 'Error: ' . $e->getMessage());
        }
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
        $products = Product::with('measurementUnit')->orderBy('name')->get();
        $isEdit   = true;

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
                'parts_total'      => $item->parts_total,
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

        return view('consignments.form', compact(
            'consignment', 'partners', 'purities', 'products', 'itemsJson', 'isEdit'
        ));
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

            // Delete only in_stock items — sold/returned are immutable
            foreach ($consignment->items()->where('item_status', 'in_stock')->get() as $item) {
                $item->parts()->delete();
                $item->delete();
            }

            // Re-create in_stock items — filter out sold/returned (submitted as hidden fields)
            $incomingItems = collect($request->items ?? [])
                ->filter(fn($i) => !in_array($i['item_status'] ?? '', ['sold', 'returned']))
                ->values()
                ->toArray();

            $this->persistItems($consignment, $incomingItems, $consignment->direction, $request);

            $consignment->recalcStatus();

            DB::commit();

            return redirect()->route('consignments.index')
                ->with('success', 'Consignment updated.');

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Consignment::update — ' . $e->getMessage());
            return back()->withInput()->with('error', 'Error: ' . $e->getMessage());
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
    // SCAN BARCODE FOR FORM
    // =========================================================================

    public function scanBarcodeForForm(Request $request)
    {
        $barcode = trim($request->get('barcode', ''));

        if (!$barcode) {
            return response()->json(['success' => false, 'message' => 'No barcode provided.'], 422);
        }

        try {
            // ── 1. Search purchase_invoice_items (MJ-/MJT- barcodes) ─────────
            $purchaseItem = \App\Models\PurchaseInvoiceItem::where('barcode_number', $barcode)
                ->latest()
                ->first();

            if ($purchaseItem) {
                $parts = [];
                try {
                    $parts = $purchaseItem->parts ?? collect();
                } catch (\Throwable $e) {
                    $parts = collect();
                }

                return response()->json([
                    'success'          => true,
                    'source'           => 'purchase',
                    'barcode_number'   => $purchaseItem->barcode_number,
                    'item_name'        => $purchaseItem->item_name,
                    'item_description' => $purchaseItem->item_description,
                    'purity'           => $purchaseItem->purity,
                    'gross_weight'     => $purchaseItem->net_weight ?? $purchaseItem->gross_weight ?? 0,
                    'making_rate'      => $purchaseItem->making_rate  ?? 0,
                    'material_type'    => $purchaseItem->material_type ?? 'gold',
                    'vat_percent'      => $purchaseItem->vat_percent   ?? 0,
                    'agreed_value'     => 0,
                    'parts'            => collect($parts)->map(fn($p) => [
                        'item_name'        => $p->item_name        ?? null,
                        'part_description' => $p->part_description ?? null,
                        'qty'              => $p->qty              ?? 0,
                        'rate'             => $p->rate             ?? 0,
                        'stone_qty'        => $p->stone_qty        ?? 0,
                        'stone_rate'       => $p->stone_rate       ?? 0,
                        'total'            => $p->total            ?? 0,
                    ])->values(),
                ]);
            }

            // ── 2. Search consignment_items (CSG- in stock) ───────────────────
            $csgItem = ConsignmentItem::with(['parts', 'consignment'])
                ->where('barcode_number', $barcode)
                ->where('item_status', 'in_stock')
                ->first();

            if ($csgItem) {
                return response()->json([
                    'success'          => true,
                    'source'           => 'consignment',
                    'consignment_no'   => $csgItem->consignment->consignment_no,
                    'barcode_number'   => $csgItem->barcode_number,
                    'item_name'        => $csgItem->item_name,
                    'item_description' => $csgItem->item_description,
                    'purity'           => $csgItem->purity,
                    'gross_weight'     => $csgItem->gross_weight,
                    'making_rate'      => $csgItem->making_rate,
                    'material_type'    => $csgItem->material_type,
                    'vat_percent'      => $csgItem->vat_percent,
                    'agreed_value'     => $csgItem->agreed_value,
                    'parts'            => $csgItem->parts->map(fn($p) => [
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

            return response()->json([
                'success' => false,
                'message' => 'Barcode "' . $barcode . '" not found. Check the barcode and try again.',
            ], 404);

        } catch (\Throwable $e) {
            Log::error('ConsignmentController::scanBarcodeForForm — ' . $e->getMessage(), [
                'barcode' => $barcode,
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Server error: ' . $e->getMessage(),
            ], 500);
        }
    }

    // =========================================================================
    // SCAN BARCODE — called from SaleInvoiceController (CSG- items only)
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
    // DOWNLOAD EXCEL TEMPLATE
    // =========================================================================

    public function downloadTemplate()
    {
        $filename = 'consignment_import_template.csv';

        $rows = [
            [
                'Item Name', 'Description', 'Purity', 'Gross Wt',
                'Making Rate', 'Material', 'VAT %', 'Agreed Value',
                'Part Name', 'Part Desc', 'Part Qty', 'Part Rate',
                'Stone Qty', 'Stone Rate',
            ],
            ['18K Gold Bracelet', 'Handmade Chain Design', '0.75', '12.50', '25.00', 'gold', '5', '0',
             '', '', '', '', '', ''],
            ['Diamond Ring', 'Solitaire Setting', '0.75', '4.20', '150.00', 'gold', '5', '0',
             '', '', '', '', '', ''],
            ['', '', '', '', '', '', '', '',
             'Main Diamond', '1.0ct GIA', '1.00', '8500', '0', '0'],
            ['', '', '', '', '', '', '', '',
             'Side Stones', 'Micro Pave', '0.50', '1200', '24', '10'],
            ['22K Bangle', 'Plain Polished', '0.92', '18.00', '10.00', 'gold', '0', '2500.00',
             '', '', '', '', '', ''],
        ];

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        }, $filename, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    // =========================================================================
    // SETTLE ITEMS — called after sale invoice saved
    // =========================================================================

    public static function settleItems(SaleInvoice $invoice): void
    {
        try {
            foreach ($invoice->items as $saleItem) {
                if (!$saleItem->barcode_number) continue;

                // Inbound: CSG- barcode scanned directly at sale
                if (str_starts_with($saleItem->barcode_number, 'CSG-')) {
                    $consignmentItem = ConsignmentItem::where('barcode_number', $saleItem->barcode_number)
                        ->where('item_status', 'in_stock')
                        ->first();

                    if ($consignmentItem) {
                        $consignmentItem->update([
                            'item_status'                => 'sold',
                            'settled_by_sale_invoice_id' => $invoice->id,
                            'settled_date'               => $invoice->invoice_date,
                        ]);
                        $consignmentItem->consignment->recalcStatus();
                    }
                    continue;
                }

                // Outbound: MJ-/MJT- barcode — match directly on source_barcode
                $consignmentItem = ConsignmentItem::where('source_barcode', $saleItem->barcode_number)
                    ->whereHas('consignment', fn($q) => $q->where('direction', 'outbound'))
                    ->where('item_status', 'in_stock')
                    ->first();

                if ($consignmentItem) {
                    $consignmentItem->update([
                        'item_status'                => 'sold',
                        'settled_by_sale_invoice_id' => $invoice->id,
                        'settled_date'               => $invoice->invoice_date,
                    ]);
                    $consignmentItem->consignment->recalcStatus();
                }
            }
        } catch (\Throwable $e) {
            Log::error('ConsignmentController::settleItems — ' . $e->getMessage());
        }
    }

    // =========================================================================
    // PRINT BARCODES — inbound only
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

        $cols  = 4;
        $cellW = 47;
        $cellH = 30;
        $col   = 0;

        foreach ($items as $item) {
            if ($col === $cols) {
                $pdf->Ln($cellH);
                $col = 0;
            }

            $x = $pdf->GetX();
            $y = $pdf->GetY();
            $pdf->Rect($x, $y, $cellW, $cellH);

            $pdf->SetXY($x + 1, $y + 1);
            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->Cell($cellW - 2, 4, $item->barcode_number, 0, 1, 'C');

            $pdf->SetXY($x + 1, $y + 6);
            $pdf->SetFont('helvetica', '', 6);
            $pdf->Cell($cellW - 2, 3, mb_substr($item->item_name ?: 'N/A', 0, 28), 0, 1, 'C');

            $pdf->SetXY($x + 1, $y + 10);
            $pdf->Cell($cellW - 2, 3,
                'GW: ' . number_format($item->gross_weight, 3) . 'g  |  Purity: ' . number_format($item->purity, 3),
                0, 1, 'C');

            $pdf->SetXY($x + 1, $y + 14);
            $pdf->Cell($cellW - 2, 3, 'AED ' . number_format($item->agreed_value, 2), 0, 1, 'C');

            $pdf->write1DBarcode(
                $item->barcode_number, 'C128',
                $x + 2, $y + 18,
                $cellW - 4, 8,
                0.4,
                ['fgcolor' => [0, 0, 0], 'bgcolor' => false, 'text' => false, 'padding' => 0]
            );

            $item->update(['is_printed' => true]);

            $pdf->SetXY($x + $cellW, $y);
            $col++;
        }

        return $pdf->Output($consignment->consignment_no . '_barcodes.pdf', 'I');
    }

    // =========================================================================
    // PRINT — Consignment Document
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

        $docTitle = $consignment->direction === 'inbound'
            ? 'CONSIGNMENT RECEIPT NOTE (Inbound)'
            : 'CONSIGNMENT DELIVERY NOTE (Outbound)';

        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 7, $docTitle, 0, 1, 'C');
        $pdf->Ln(2);
        $pdf->SetFont('helvetica', '', 9);

        $direction = $consignment->direction === 'inbound' ? 'Received From' : 'Delivered To';

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
                <tr><td><strong>Start Date</strong></td><td>' . $consignment->start_date->format('d.m.Y') . '</td></tr>
                <tr><td><strong>End Date</strong></td><td>' . ($consignment->end_date ? $consignment->end_date->format('d.m.Y') : 'Open-ended') . '</td></tr>
                <tr><td><strong>Duration</strong></td><td>' . ($consignment->duration_label ?: '-') . '</td></tr>
                <tr><td><strong>Status</strong></td><td>' . ucwords(str_replace('_', ' ', $consignment->status)) . '</td></tr>
            </table>
            </td>
        </tr>
        </table>', true, false, false, false);
        $pdf->Ln(3);

        // =========================================================================
        // Column layout — total must equal 100%
        //
        //  #        Item Name   Description  Purity  GrossWt  PurityWt  995   MkRate  MkVal  Material  MatVal  AgreedVal  Status
        //  3%       13%         13%          5%      6%       6%        5%    6%      7%     6%        8%      9%         7%
        //  = 3+13+13+5+6+6+5+6+7+6+8+9+7 = 100%
        //
        //  Making colspan=2 covers MkRate(6%) + MkVal(7%) = 13%
        // =========================================================================

        $html = '
        <table border="1" cellpadding="2" width="100%" style="">
            <thead>
                <tr style="font-weight:bold;background-color:#f0f0f0;text-align:center;">
                    <th width="3%"  rowspan="2">#</th>
                    <th width="11%" rowspan="2">Item Name</th>
                    <th width="14%" rowspan="2">Description</th>
                    <th width="6%"  rowspan="2">Purity</th>
                    <th width="6%"  rowspan="2">Gross Wt</th>
                    <th width="6%"  rowspan="2">Purity Wt</th>
                    <th width="6%"  rowspan="2">995</th>
                    <th width="13%" colspan="2" style="text-align:center;">Making</th>
                    <th width="8%"  rowspan="2">Material</th>
                    <th width="9%"  rowspan="2">Material Val</th>
                    <th width="8%"  rowspan="2">Agreed Val</th>
                    <th width="8%"  rowspan="2">Status</th>
                </tr>
                <tr style="font-weight:bold;background-color:#f0f0f0;text-align:center;">
                    <th width="6%">Rate</th>
                    <th width="7%">Value</th>
                </tr>
            </thead>
            <tbody>';

        $totGross = $totMaking = $totMaterial = $totAgreed = 0;

        foreach ($consignment->items as $index => $item) {
            $hasParts = $item->parts && $item->parts->count() > 0;
            $bg = match($item->item_status) {
                'sold'     => '#d4edda',
                'returned' => '#fff3cd',
                default    => '#ffffff',
            };

            $html .= '
            <tr style="text-align:center;background-color:' . $bg . ';">
                <td width="3%"  style="text-align:center;">'                             . ($index + 1) . '</td>
                <td width="11%" style="text-align:left;">'   . htmlspecialchars($item->item_name        ?? '-') . '</td>
                <td width="14%" style="text-align:left;">'   . htmlspecialchars($item->item_description ?? '-') . '</td>
                <td width="6%"  style="text-align:center;">' . number_format($item->purity,        3) . '</td>
                <td width="6%"  style="text-align:center;">' . number_format($item->gross_weight,  3) . '</td>
                <td width="6%"  style="text-align:center;">' . number_format($item->purity_weight, 3) . '</td>
                <td width="6%"  style="text-align:center;">' . number_format($item->col_995,       3) . '</td>
                <td width="6%"  style="text-align:right;">'  . number_format($item->making_rate,   2) . '</td>
                <td width="7%"  style="text-align:right;">'  . number_format($item->making_value,  2) . '</td>
                <td width="8%"  style="text-align:center;">' . ucfirst($item->material_type)           . '</td>
                <td width="9%"  style="text-align:right;">'  . number_format($item->material_value, 2) . '</td>
                <td width="8%"  style="text-align:right;font-weight:bold;">' . number_format($item->agreed_value, 2) . '</td>
                <td width="8%"  style="text-align:center;">' . ucfirst($item->item_status)             . '</td>
            </tr>';

            if ($hasParts) {
                $html .= '
                <tr style="background-color:#f5f5f5">
                    <td></td>
                    <td colspan="12" style="text-align:left;font-style:italic;padding-left:4px;">
                        <b>Parts:</b>
                    </td>
                </tr>';

                foreach ($item->parts as $part) {
                    $html .= '
                    <tr style="background-color:#fafafa;text-align:center;">
                        <td></td>
                        <td colspan="1" style="text-align:left;">'  . htmlspecialchars($part->item_name        ?? 'Part') . '</td>
                        <td colspan="2" style="text-align:left;">'  . htmlspecialchars($part->part_description ?? '')     . '</td>
                        <td colspan="2" style="text-align:center;">'
                            . number_format($part->qty, 3) . ' Ct @ ' . number_format($part->rate, 2)
                        . '</td>
                        <td colspan="2" style="text-align:center;">St.' . number_format($part->stone_qty  ?? 0, 2) . '</td>
                        <td colspan="2" style="text-align:center;">SR:' . number_format($part->stone_rate ?? 0, 2) . '</td>
                        <td colspan="2" style="text-align:right;font-weight:bold;">' . number_format($part->total, 2) . '</td>
                    </tr>';
                }
            }

            $totGross    += $item->gross_weight;
            $totMaking   += $item->making_value;
            $totMaterial += $item->material_value;
            $totAgreed   += $item->agreed_value;
        }

        $html .= '
            <tr style="font-weight:bold;background-color:#f0f0f0;text-align:center;">
                <td colspan="4" style="text-align:right;">TOTAL</td>
                <td style="text-align:center;">' . number_format($totGross,    3) . '</td>
                <td></td>
                <td></td>
                <td></td>
                <td style="text-align:right;">'  . number_format($totMaking,   2) . '</td>
                <td></td>
                <td style="text-align:right;">'  . number_format($totMaterial, 2) . '</td>
                <td style="text-align:right;">'  . number_format($totAgreed,   2) . '</td>
                <td></td>
            </tr>
            </tbody>
        </table>';

        $pdf->writeHTML($html, true, false, false, false);

        if ($consignment->remarks) {
            $pdf->Ln(3);
            $pdf->SetFont('helvetica', '', 8);
            $pdf->MultiCell(0, 4, 'Remarks: ' . $consignment->remarks, 0, 'L');
        }

        $pdf->Ln(20);
        $y = $pdf->GetY();
        $pdf->Line(20,  $y, 85,  $y);
        $pdf->Line(125, $y, 190, $y);
        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetXY(20,  $y + 1); $pdf->Cell(65, 5, 'Partner Signature',    0, 0, 'C');
        $pdf->SetXY(125, $y + 1); $pdf->Cell(65, 5, 'Authorized Signature', 0, 0, 'C');

        return $pdf->Output($consignment->consignment_no . '.pdf', 'I');
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    private function validateConsignment(Request $request, bool $updating = false): void
    {
        $rules = [
            'partner_id'              => 'required|exists:chart_of_accounts,id',
            'start_date'              => 'required|date',
            'end_date'                => 'nullable|date|after_or_equal:start_date',
            'duration_label'          => 'nullable|string|max:60',
            'remarks'                 => 'nullable|string',
            'items'                   => 'required|array|min:1',
            'items.*.gross_weight'    => 'required|numeric|min:0',
            'items.*.purity'          => 'required|numeric|min:0|max:1',
            'items.*.making_rate'     => 'required|numeric|min:0',
            'items.*.material_type'   => 'required|in:gold,diamond',
            'items.*.agreed_value'    => 'required|numeric|min:0',
        ];

        if (!$updating) {
            $rules['direction'] = 'required|in:inbound,outbound';
        }

        $request->validate($rules);
    }

    /**
     * Generate a barcode number for a consignment item.
     *
     * Format mirrors purchase invoice barcodes exactly:
     *   Purchase:    MJT-00001-1   = prefix + invoice_seq + item_position
     *   Consignment: CSG-00001-1   = prefix + consignment_seq + item_position
     *
     * The consignment_no (e.g. CSG-IN-00001 or CSG-OUT-00001) already contains
     * the zero-padded sequence as its last segment. We extract it and prepend
     * the short CSG- prefix so the barcode stays compact and scanner-friendly.
     */
    private function generateConsignmentBarcode(Consignment $consignment, int $itemPosition): string
    {
        // Extract the numeric sequence from the end of consignment_no.
        // CSG-IN-00001  → 00001
        // CSG-OUT-00003 → 00003
        $seqPart = substr($consignment->consignment_no, strrpos($consignment->consignment_no, '-') + 1);

        // Result: CSG-00001-1, CSG-00001-2, CSG-00002-1 …
        return 'CSG-' . $seqPart . '-' . $itemPosition;
    }

    /**
     * Persist ConsignmentItem + ConsignmentItemPart rows.
     */
    private function persistItems(Consignment $consignment, array $items, string $direction, Request $request): void
    {
        $goldRateAed = (float) ($request->gold_rate_aed    ?? 0);
        $diaRateAed  = (float) ($request->diamond_rate_aed ?? 0);

        // Item position counter — mirrors purchase invoice's $position variable.
        // Starts at 1 for each consignment so barcodes are CSG-00001-1, -2, -3 …
        $itemPosition = 1;

        foreach ($items as $itemData) {
            $baseGross   = (float) ($itemData['gross_weight']  ?? 0);
            $purity      = (float) ($itemData['purity']        ?? 0);
            $makingRate  = (float) ($itemData['making_rate']   ?? 0);
            $vatPercent  = (float) ($itemData['vat_percent']   ?? 0);
            $matType     = $itemData['material_type']           ?? 'gold';
            $agreedValue = (float) ($itemData['agreed_value']  ?? 0);

            $itemGoldR = (float) ($itemData['gold_rate_aed']    ?? $goldRateAed);
            $itemDiaR  = (float) ($itemData['diamond_rate_aed'] ?? $diaRateAed);

            // ── CTS-adjusted gross weight ─────────────────────────────────────
            $partsData       = $itemData['parts'] ?? [];
            $totalDiamondCTS = 0.0;
            $totalStoneCTS   = 0.0;
            foreach ($partsData as $p) {
                $totalDiamondCTS += (float) ($p['qty']       ?? 0);
                $totalStoneCTS   += (float) ($p['stone_qty'] ?? 0);
            }
            $calcGross = $baseGross + ($totalDiamondCTS / 5) + ($totalStoneCTS / 5);

            // ── Core calculations ─────────────────────────────────────────────
            $purityWeight  = $calcGross * $purity;
            $col995        = $purityWeight > 0 ? $purityWeight / 0.995 : 0;
            $makingValue   = $calcGross * $makingRate;
            $rate          = $matType === 'gold' ? $itemGoldR : $itemDiaR;
            $materialValue = $rate * $purityWeight;

            // ── Parts total ───────────────────────────────────────────────────
            $partsTotal = 0.0;
            foreach ($partsData as $p) {
                $partsTotal += ((float) ($p['qty']       ?? 0) * (float) ($p['rate']       ?? 0))
                             + ((float) ($p['stone_qty'] ?? 0) * (float) ($p['stone_rate'] ?? 0));
            }

            $taxableAmount = $makingValue;
            $vatAmount     = $taxableAmount * ($vatPercent / 100);

            if ($agreedValue <= 0) {
                $agreedValue = $materialValue + $makingValue + $partsTotal + $vatAmount;
            }

            $item = $consignment->items()->create([
                'item_name'        => $itemData['item_name']        ?? null,
                'product_id'       => $itemData['product_id']       ?? null,
                'item_description' => $itemData['item_description'] ?? null,
                'barcode_number'   => null,
                'source_barcode'   => ($direction === 'outbound') ? ($itemData['source_barcode'] ?? null) : null,
                'is_printed'       => false,
                'gross_weight'     => $baseGross,
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

            // ── Generate barcode for inbound items ────────────────────────────
            // Format: CSG-00001-1  (same structure as MJT-00001-1 in purchase)
            // Only inbound items get barcodes — outbound items are ours,
            // already have purchase barcodes and don't need new ones.
            if ($direction === 'inbound') {
                $item->update([
                    'barcode_number' => $this->generateConsignmentBarcode($consignment, $itemPosition),
                ]);
            }

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

            $itemPosition++;
        }
    }

    // =========================================================================
// SHOW
// =========================================================================

public function show($id)
{
    $consignment = Consignment::with([
        'partner',
        'items.parts',
        'items.settledBySaleInvoice',
    ])->findOrFail($id);

    return view('consignments.show', compact('consignment'));
}

    // =========================================================================
    // MARK SINGLE ITEM AS RETURNED — updated to stamp settled_date
    // =========================================================================

    public function returnItem($consignmentId, $itemId)
    {
        $item = ConsignmentItem::where('consignment_id', $consignmentId)
            ->where('id', $itemId)
            ->where('item_status', 'in_stock')
            ->firstOrFail();

        $item->update([
            'item_status'  => 'returned',
            'settled_date' => now()->toDateString(),
        ]);

        $item->consignment->recalcStatus();

        return back()->with('success', 'Item marked as returned.');
    }

    // =========================================================================
    // MARK ALL PENDING ITEMS AS RETURNED
    // =========================================================================

    public function returnAll($consignmentId)
    {
        $consignment = Consignment::findOrFail($consignmentId);

        $pendingItems = $consignment->items()
            ->where('item_status', 'in_stock')
            ->get();

        if ($pendingItems->isEmpty()) {
            return back()->with('error', 'No pending items to return.');
        }

        foreach ($pendingItems as $item) {
            $item->update([
                'item_status'  => 'returned',
                'settled_date' => now()->toDateString(),
            ]);
        }

        $consignment->recalcStatus();

        return back()->with('success', $pendingItems->count() . ' item(s) marked as returned.');
    }
}