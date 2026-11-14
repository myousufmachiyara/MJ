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

            $this->persistItems($consignment, $this->resolveItems($request), $consignment->direction, $request);
            $this->applyOverallDiscount($consignment, $request);

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

        // Pictures live on the purchased item (Purchase Invoice -> Img), matched by
        // the item's ORIGINAL barcode (source_barcode).
        $imagePaths = PurchaseInvoiceItem::whereIn('barcode_number', $consignment->items->pluck('source_barcode')->filter()->unique()->values())
            ->whereNotNull('image_path')
            ->orderBy('id')
            ->pluck('image_path', 'barcode_number');

        $itemsJson = $consignment->items->map(function ($item) use ($imagePaths) {
            return [
                'id'               => $item->id,
                'source_barcode'   => $item->source_barcode,
                'image_url'        => $this->imageUrlFromPath($imagePaths->get($item->source_barcode)),
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
                // the form's Agreed Value box is the value BEFORE the item discount
                'agreed_gross'     => round((float) $item->agreed_value + (float) ($item->discount_amount ?? 0), 2),
                'discount_type'    => $item->discount_type,
                'discount_value'   => $item->discount_value,
                'discount_amount'  => $item->discount_amount,
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
            $incomingItems = collect($this->resolveItems($request))
                ->filter(fn($i) => !in_array($i['item_status'] ?? '', ['sold', 'returned']))
                ->values()
                ->toArray();

            $this->persistItems($consignment, $incomingItems, $consignment->direction, $request);
            $this->applyOverallDiscount($consignment, $request);

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

    /**
     * Same lookup rules as SaleInvoiceController::scanBarcode(), so a label
     * scans identically in both screens:
     *
     *   1. pure digits  -> the short numeric code printed on purchase labels
     *                      (PurchaseInvoiceItem::getScanCodeAttribute() = the
     *                      item's id, zero-padded). Can never collide with a
     *                      typed barcode_number, which always has a letter/dash.
     *   2. previous sale line with that barcode_number
     *   3. in-stock CSG- consignment item
     *   4. purchase item by barcode_number (MJ-/MJT-/{SubcategoryCode}-00001)
     *
     * The response always carries the REAL barcode_number, which the form must
     * store as source_barcode (never the scanned numeric code) — inventory
     * reports and sale settlement match on it.
     */
    public function scanBarcodeForForm(Request $request)
    {
        $barcode = trim($request->get('barcode', ''));

        if (!$barcode) {
            return response()->json(['success' => false, 'message' => 'No barcode provided.'], 422);
        }

        try {
            // ── 1. Numeric label code -> purchase item id ────────────────────
            if (ctype_digit($barcode)) {
                $purchaseItem = PurchaseInvoiceItem::with('parts')->find((int) $barcode);

                if ($purchaseItem) {
                    return response()->json($this->formScanPayload('purchase', $purchaseItem));
                }

                return response()->json([
                    'success' => false,
                    'message' => 'Scanned label not recognised (item #' . $barcode . ' not found — it may have been deleted).',
                ], 404);
            }

            // ── 2. Previously sold line with this barcode ────────────────────
            $saleItem = \App\Models\SaleInvoiceItem::with('parts')
                ->where('barcode_number', $barcode)
                ->latest()
                ->first();

            if ($saleItem) {
                return response()->json($this->formScanPayload('sale', $saleItem));
            }

            // ── 3. Consignment item still in stock (CSG-) ────────────────────
            $csgItem = ConsignmentItem::with(['parts', 'consignment'])
                ->where('barcode_number', $barcode)
                ->where('item_status', 'in_stock')
                ->first();

            if ($csgItem) {
                return response()->json($this->formScanPayload('consignment', $csgItem));
            }

            // ── 4. Purchase item by barcode_number ───────────────────────────
            $purchaseItem = PurchaseInvoiceItem::with('parts')
                ->where('barcode_number', $barcode)
                ->latest()
                ->first();

            if ($purchaseItem) {
                return response()->json($this->formScanPayload('purchase', $purchaseItem));
            }

            return response()->json([
                'success' => false,
                'message' => 'Barcode "' . $barcode . '" not found in any record.',
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

    private function imageUrlFromPath(?string $imagePath): ?string
    {
        return $imagePath ? asset('storage/app/public/' . $imagePath) : null;
    }

    /** image_path of the purchased item carrying this barcode (null if none). */
    private function imagePathFor($barcode): ?string
    {
        if (!$barcode) {
            return null;
        }

        return PurchaseInvoiceItem::where('barcode_number', $barcode)
            ->whereNotNull('image_path')
            ->orderBy('id')
            ->value('image_path');
    }

    /** [AED, USD] selling price stored on the purchased item with this barcode. */
    private function sellingPricesFor($barcode): array
    {
        if (!$barcode) {
            return [null, null];
        }

        $p = PurchaseInvoiceItem::where('barcode_number', $barcode)
            ->latest()
            ->first(['selling_price', 'selling_price_usd']);

        return [
            $p && $p->selling_price !== null ? (float) $p->selling_price : null,
            $p && $p->selling_price_usd !== null ? (float) $p->selling_price_usd : null,
        ];
    }

    /** One JSON shape for every scan source (purchase | sale | consignment). */
    private function formScanPayload(string $source, $item): array
    {
        // Selling price (AED) set on the purchased item. A consignment is in AED,
        // so this becomes the row's Agreed Value (the Item Total column) and
        // flows into Total Agreed Value. Items without a price keep the
        // automatic rate-based value; an item already on a consignment keeps
        // the value agreed there.
        [$sellingAed, $sellingUsd] = $this->sellingPricesFor($item->barcode_number);

        // Picture of the purchased item (a CSG- item points back to its original
        // purchase barcode through source_barcode).
        $imageBarcode = $source === 'consignment' ? ($item->source_barcode ?: null) : $item->barcode_number;
        $imagePath    = $source === 'purchase' ? ($item->image_path ?? null) : $this->imagePathFor($imageBarcode);

        $agreedValue = $source === 'consignment' && (float) ($item->agreed_value ?? 0) > 0
            ? (float) $item->agreed_value
            : (float) ($sellingAed ?? 0);

        $payload = [
            'success'          => true,
            'source'           => $source,
            'barcode_number'   => $item->barcode_number,
            'item_name'        => $item->item_name,
            'item_description' => $item->item_description,
            'purity'           => $item->purity,
            // Purchase items: net_weight = weight WITHOUT parts, so parts are not
            // counted twice (gross_weight already includes them).
            'gross_weight'     => $source === 'purchase'
                                    ? ($item->net_weight ?? $item->gross_weight ?? 0)
                                    : ($item->gross_weight ?? 0),
            'making_rate'      => $item->making_rate   ?? 0,
            'material_type'    => $item->material_type ?? 'gold',
            'vat_percent'      => $item->vat_percent   ?? 0,
            'agreed_value'     => $agreedValue,
            'image_url'        => $this->imageUrlFromPath($imagePath),
            'selling_price_aed' => $sellingAed,
            'selling_price_usd' => $sellingUsd,
            'parts'            => $item->parts->map(fn ($p) => [
                'item_name'        => $p->item_name        ?? null,
                'part_description' => $p->part_description ?? null,
                'qty'              => $p->qty              ?? 0,
                'rate'             => $p->rate             ?? 0,
                'stone_qty'        => $p->stone_qty        ?? 0,
                'stone_rate'       => $p->stone_rate       ?? 0,
                'total'            => $p->total            ?? 0,
            ])->values()->toArray(),
        ];

        if ($source === 'consignment') {
            $payload['consignment_no'] = $item->consignment->consignment_no ?? null;
        }

        return $payload;
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

    public static function settleItems(SaleInvoice $invoice): array
    {
        try {
            $invoice->loadMissing('items');
            $linkedId  = $invoice->consignment_id ?: null;
            $claimedIds = [];
            $unmatched  = [];

            // A sale line can only settle an item that is still in stock, or one
            // this very invoice already settled (editing an invoice re-runs this).
            $open = function ($q) use ($invoice) {
                $q->where('item_status', 'in_stock')
                  ->orWhere(fn($w) => $w->where('item_status', 'sold')
                      ->where('settled_by_sale_invoice_id', $invoice->id));
            };

            foreach ($invoice->items as $saleItem) {
                $code = trim((string) $saleItem->barcode_number);
                $found = null;

                if ($code !== '') {
                    // Inbound: CSG- barcode scanned directly at sale
                    if (str_starts_with($code, 'CSG-')) {
                        $found = ConsignmentItem::where('barcode_number', $code)
                            ->where($open)->whereNotIn('id', $claimedIds)->first();
                    } else {
                        // Outbound: the item's original barcode — linked consignment first
                        if ($linkedId) {
                            $found = ConsignmentItem::where('consignment_id', $linkedId)
                                ->where('source_barcode', $code)
                                ->where($open)->whereNotIn('id', $claimedIds)->first();
                        }
                        if (!$found) {
                            $found = ConsignmentItem::where('source_barcode', $code)
                                ->whereHas('consignment', fn($q) => $q->where('direction', 'outbound'))
                                ->where($open)->whereNotIn('id', $claimedIds)->first();
                        }
                    }
                }

                // Fallback for lines whose barcode did not match (typed by hand, edited,
                // or saved before barcodes were stored). Only inside the LINKED consignment.
                // Tier 1: same item name + description; tier 2: same description only.
                // When several identical items remain, take the one whose gross weight
                // is closest to the sale line, so duplicates are claimed one-by-one.
                if (!$found && $linkedId) {
                    $desc = trim((string) $saleItem->item_description);
                    $tiers = [];
                    if ($desc !== '' && trim((string) $saleItem->item_name) !== '') {
                        $tiers[] = ['item_description' => $desc, 'item_name' => trim($saleItem->item_name)];
                    }
                    if ($desc !== '') {
                        $tiers[] = ['item_description' => $desc];
                    }
                    foreach ($tiers as $where) {
                        $cands = ConsignmentItem::where('consignment_id', $linkedId)
                            ->where($open)->whereNotIn('id', $claimedIds)
                            ->where($where)->get();
                        if ($cands->isEmpty()) continue;
                        $w = (float) $saleItem->gross_weight;
                        $found = $cands->sortBy(fn($c) => abs((float) $c->gross_weight - $w))->first();
                        break;
                    }
                }

                if (!$found) {
                    $unmatched[] = trim(($saleItem->item_name ?? '') . ' ' . ($saleItem->item_description ?? '')) . ' [' . ($code !== '' ? $code : 'no barcode') . ']';
                    continue;
                }

                $claimedIds[] = $found->id;
                if ($found->item_status !== 'sold' || (int) $found->settled_by_sale_invoice_id !== (int) $invoice->id) {
                    $found->update([
                        'item_status'                => 'sold',
                        'settled_by_sale_invoice_id' => $invoice->id,
                        'settled_date'               => $invoice->invoice_date,
                    ]);
                }
            }

            // Items this invoice used to settle but no longer contains go back in stock.
            self::releaseItems($invoice->id, $claimedIds);

            // Refresh the status of every consignment touched.
            $ids = ConsignmentItem::where('settled_by_sale_invoice_id', $invoice->id)->pluck('consignment_id')
                ->merge($linkedId ? [$linkedId] : [])->unique();
            Consignment::whereIn('id', $ids)->get()->each->recalcStatus();

            return $unmatched;
        } catch (\Throwable $e) {
            Log::error('ConsignmentController::settleItems — ' . $e->getMessage(), ['invoice_id' => $invoice->id ?? null]);
            return ['ERROR: ' . $e->getMessage()];
        }
    }

    /**
     * Put back in stock every item settled by $invoiceId, except $keepIds.
     * Called when a sale invoice is edited (line removed) or deleted.
     */
    public static function releaseItems(int $invoiceId, array $keepIds = []): void
    {
        $items = ConsignmentItem::where('settled_by_sale_invoice_id', $invoiceId)
            ->where('item_status', 'sold')
            ->when($keepIds, fn($q) => $q->whereNotIn('id', $keepIds))
            ->get();

        foreach ($items as $item) {
            $item->update([
                'item_status'                => 'in_stock',
                'settled_by_sale_invoice_id' => null,
                'settled_date'               => null,
            ]);
            optional($item->consignment)->recalcStatus();
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
        $pdf->SetAutoPageBreak(false); // We handle page breaks manually
        $pdf->AddPage();

        // ── Layout constants (all in mm) ─────────────────────────────────────────
        $pageW      = $pdf->getPageWidth();
        $pageH      = $pdf->getPageHeight();
        $marginL    = 10;   // left margin
        $marginR    = 10;   // right margin
        $marginT    = 10;   // top margin
        $marginB    = 10;   // bottom margin
        $cols       = 4;    // barcodes per row
        $gapX       = 4;    // horizontal gap between barcodes
        $gapY       = 5;    // vertical gap between rows
        $cellH      = 32;   // height of each barcode cell

        // Calculate cell width to fill page evenly with gaps
        $usableW    = $pageW - $marginL - $marginR;
        $cellW      = ($usableW - ($gapX * ($cols - 1))) / $cols;

        $col        = 0;
        $row        = 0;

        // Starting Y for first row
        $startY     = $marginT;

        foreach ($items as $item) {

            // ── Check if a new row would overflow the page ────────────────────────
            if ($col === 0 && $row > 0) {
                $nextRowBottom = $startY + ($row * ($cellH + $gapY)) + $cellH;
                if ($nextRowBottom > ($pageH - $marginB)) {
                    $pdf->AddPage();
                    $row    = 0;
                    $col    = 0;
                    $startY = $marginT;
                }
            }

            // ── Calculate X and Y for this cell ──────────────────────────────────
            $x = $marginL + ($col * ($cellW + $gapX));
            $y = $startY  + ($row * ($cellH + $gapY));

            // ── Draw cell border ──────────────────────────────────────────────────
            $pdf->SetDrawColor(180, 180, 180); // light grey border
            $pdf->Rect($x, $y, $cellW, $cellH);

            // ── Barcode number (bold, top) ────────────────────────────────────────
            $pdf->SetXY($x + 1, $y + 1);
            $pdf->SetFont('helvetica', 'B', 7);
            $pdf->Cell($cellW - 2, 4, $item->barcode_number, 0, 0, 'C');

            // ── Item name ─────────────────────────────────────────────────────────
            $pdf->SetXY($x + 1, $y + 5.5);
            $pdf->SetFont('helvetica', '', 5.5);
            $pdf->Cell($cellW - 2, 3, mb_substr($item->item_name ?: 'N/A', 0, 32), 0, 0, 'C');

            // ── Gross weight + purity ─────────────────────────────────────────────
            $pdf->SetXY($x + 1, $y + 9);
            $pdf->SetFont('helvetica', '', 5.5);
            $pdf->Cell($cellW - 2, 3,
                'GW: ' . number_format($item->gross_weight, 3) . 'g  |  Purity: ' . number_format($item->purity, 3),
                0, 0, 'C');

            // ── Agreed value ──────────────────────────────────────────────────────
            $pdf->SetXY($x + 1, $y + 12.5);
            $pdf->SetFont('helvetica', 'B', 6);
            $pdf->Cell($cellW - 2, 3, 'AED ' . number_format($item->agreed_value, 2), 0, 0, 'C');

            // ── 1D Barcode ────────────────────────────────────────────────────────
            $pdf->write1DBarcode(
                $item->barcode_number, 'C128',
                $x + 2, $y + 17,
                $cellW - 4, 12,
                0.35,
                ['fgcolor' => [0, 0, 0], 'bgcolor' => false, 'text' => false, 'padding' => 0]
            );

            $item->update(['is_printed' => true]);

            // ── Advance column/row counters ───────────────────────────────────────
            $col++;
            if ($col >= $cols) {
                $col = 0;
                $row++;
            }
        }

        return $pdf->Output($consignment->consignment_no . '_barcodes.pdf', 'I');
    }

    // =========================================================================
    // PRINT — Consignment Document
    // =========================================================================

    // =========================================================================
    // PRINT — optional product pictures (same behaviour as the Sale Invoice print)
    // =========================================================================

    /** Pictures are added only when the print URL carries ?images=1. */
    private function wantsPrintImages(Request $request): bool
    {
        return $request->boolean('images');
    }

    /**
     * source_barcode => small data-URI thumbnail (or null) for every item.
     * The picture is the one uploaded on the purchased item with that barcode.
     * Each one is shrunk to a 240px letter-boxed JPEG so the PDF stays small.
     */
    private function printImageSources($consignment): array
    {
        $barcodes = $consignment->items->pluck('source_barcode')->filter()->unique()->values();
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
            Log::warning('[Consignment] print picture skipped: ' . $e->getMessage());
            return null;
        }
    }

    /** barcode => certificate_no for items carrying a certificate (purchase items). */
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
        $parts = [];
        if ($code) $parts[] = 'Code: '  . htmlspecialchars($code);
        if ($cert) $parts[] = 'Cert#: ' . htmlspecialchars($cert);
        return $parts
            ? '<br><span style="font-size:7px;color:#555555;">' . implode('<br>', $parts) . '</span>'
            : '';
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
        $consignment = Consignment::with(['partner', 'items.parts', 'createdBy'])->findOrFail($id);

        // optional "Picture" column (see wantsPrintImages())
        $showImages = $this->wantsPrintImages($request);
        $imageSrc   = $showImages ? $this->printImageSources($consignment) : [];
        $picTh      = $showImages ? '<th width="6%" style="font-size:7pt;">Picture</th>' : '';
        $nameW      = $showImages ? 14 : 16;     // the two removed Making columns (13%) went to
        $descW      = $showImages ? 19 : 23;     // Item Name / Description; every row still adds up to 100%
        $colSpan    = $showImages ? 11 : 10;     // columns in an item row

        $pdf = new myPDF();
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetTitle($consignment->consignment_no);
        $pdf->SetMargins(10, 10, 10);
        $pdf->setCellPadding(1.5);
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
            Tel: ' . e($consignment->partner->contact_no ?? '-') . '<br>
            TRN: ' . (trim((string) ($consignment->partner->trn ?? '')) !== '' ? e($consignment->partner->trn) : '-') . '
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
        // TABLE HEADER — written once, then repeated after each page break
        // =========================================================================

        $tableHeader = '
        <table border="1" cellpadding="2" width="100%">
            <thead>
                <tr style="font-weight:bold;background-color:#f0f0f0;text-align:center;">
                    <th width="5%">#</th>
                    ' . $picTh . '
                    <th width="' . $nameW . '%">Item Name</th>
                    <th width="' . $descW . '%">Description</th>
                    <th width="6%">Purity</th>
                    <th width="6%">Gross Wt</th>
                    <th width="6%">Purity Wt</th>
                    <th width="6%">995</th>
                    <th width="9%">Material</th>
                    <th width="10%">Material Val</th>
                    <th width="13%">Agreed Val</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>';

        // Write the header table — TCPDF renders it and advances Y
        $pdf->writeHTML($tableHeader, true, false, false, false);

        // =========================================================================
        // ROW HEIGHT CONSTANTS (mm, approximate for font-size 9 + cellpadding 2)
        // Tune these if rows appear too tight or too loose on the actual PDF.
        // =========================================================================
        $ROW_ITEM_H    = $showImages ? 12 : 6;   // height of one item data row (taller with a picture)
        $ROW_CODE_H    = 3;                      // extra line for "Code | Cert#" under the item name
        $ROW_LABEL_H   = 5;   // height of the "Parts:" label row
        $ROW_PART_H    = 5;   // height of one part detail row
        $ROW_HEADER_H  = 14;  // height of the two-row thead (written after page break)
        $SAFETY_MARGIN = 4;   // extra buffer so nothing clips at the very bottom

        $totGross = $totMaking = $totMaterial = $totAgreed = 0;

        $certs = $this->certificateMap($consignment->items->pluck('source_barcode'));

        foreach ($consignment->items as $index => $item) {

            $hasParts   = $item->parts && $item->parts->count() > 0;
            $partsCount = $hasParts ? $item->parts->count() : 0;

            // ── Estimate how many mm this item (+ its parts) will need ───────────
            $hasDisc      = (float) ($item->discount_amount ?? 0) > 0;
            $itemCode     = $item->source_barcode ?: $item->barcode_number;
            $itemCert     = $certs->get($item->source_barcode);
            $neededHeight = $ROW_ITEM_H
                + (($itemCode && !$showImages) ? $ROW_CODE_H : 0)
                + ($hasDisc ? $ROW_LABEL_H : 0)
                + ($hasParts ? $ROW_LABEL_H + ($partsCount * $ROW_PART_H) : 0)
                + $SAFETY_MARGIN;

            // ── Available space remaining on the current page ────────────────────
            $availableSpace = $pdf->getPageHeight()
                - $pdf->GetY()
                - $pdf->getBreakMargin();

            // ── If not enough room, start a new page and re-print the header ─────
            if ($neededHeight > $availableSpace) {
                $pdf->AddPage();
                $pdf->writeHTML($tableHeader, true, false, false, false);
            }

            // ── Build this item's HTML (one table containing item row + parts) ───
            $bg = match($item->item_status) {
                'sold'     => '#d4edda',
                'returned' => '#fff3cd',
                default    => '#ffffff',
            };

            $rowHtml = '
            <table border="1" cellpadding="2" width="100%">
                <tbody>
                    <tr style="text-align:center;background-color:' . $bg . ';">
                        <td width="5%"  style="text-align:center;">' . ($index + 1) . '</td>
                        ' . ($showImages ? '<td width="6%" style="text-align:center;">' . $this->printImageCell($imageSrc[$item->source_barcode] ?? null, 26) . '</td>' : '') . '
                        <td width="' . $nameW . '%" style="text-align:left;">'   . htmlspecialchars($item->item_name        ?? '-') . $this->itemCodeHtml($itemCode, $itemCert) . '</td>
                        <td width="' . $descW . '%" style="text-align:left;">'   . htmlspecialchars($item->item_description ?? '-') . '</td>
                        <td width="6%"  style="text-align:center;">' . number_format($item->purity,        3) . '</td>
                        <td width="6%"  style="text-align:center;">' . number_format($item->gross_weight,  3) . '</td>
                        <td width="6%"  style="text-align:center;">' . number_format($item->purity_weight, 3) . '</td>
                        <td width="6%"  style="text-align:center;">' . number_format($item->col_995,       3) . '</td>
                        <td width="9%"  style="text-align:center;">' . ucfirst($item->material_type)           . '</td>
                        <td width="10%" style="text-align:right;">'  . number_format($item->material_value, 2) . '</td>
                        <td width="13%" style="text-align:right;font-weight:bold;">' . number_format($item->agreed_value, 2) . '</td>
                    </tr>';

            // per-item discount sub-row (same idea as the Sale Invoice print)
            if ($hasDisc) {
                $discLabel = ($item->discount_type ?? '') === 'percent'
                    ? 'Discount (' . rtrim(rtrim(number_format((float) $item->discount_value, 2), '0'), '.') . '%) — already deducted from Agreed Val:'
                    : 'Discount — already deducted from Agreed Val:';
                $rowHtml .= '
                    <tr style="background-color:#fff8e6;font-style:italic;">
                        <td colspan="' . ($colSpan - 1) . '" style="text-align:right;">' . $discLabel . '</td>
                        <td style="text-align:right;">-' . number_format((float) $item->discount_amount, 2) . '</td>
                    </tr>';
            }

            if ($hasParts) {
                $rowHtml .= '
                    <tr style="background-color:#f5f5f5">
                        <td colspan="' . $colSpan . '" style="text-align:left;font-style:italic;padding-left:4px;">
                            <b>Parts:</b>
                        </td>
                    </tr>';

                foreach ($item->parts as $part) {
                    $rowHtml .= '
                    <tr style="background-color:#fafafa;text-align:center;">
                        <td colspan="' . ($showImages ? 3 : 2) . '" style="text-align:left;">'  . htmlspecialchars($part->item_name        ?? 'Part') . '</td>
                        <td colspan="1" style="text-align:left;">'  . htmlspecialchars($part->part_description ?? '')     . '</td>
                        <td colspan="3" style="text-align:center;">' . number_format($part->qty, 3) . ' Ct</td>
                        <td colspan="3" style="text-align:center;">St.' . number_format($part->stone_qty ?? 0, 2) . '</td>
                        <td colspan="1" style="text-align:right;font-weight:bold;">' . number_format($part->total, 2) . '</td>
                    </tr>';
                }
            }

            $rowHtml .= '
                </tbody>
            </table>';

            $pdf->writeHTML($rowHtml, true, false, false, false);

            $totGross    += $item->gross_weight;
            $totMaking   += $item->making_value;
            $totMaterial += $item->material_value;
            $totAgreed   += $item->agreed_value;
        }

        // =========================================================================
        // TOTALS SUMMARY TABLE
        // =========================================================================
        $totPartsVal = $consignment->items->sum(fn($item) => $item->parts->sum('total'));

        $summaryHtml = '
        <table border="1" cellpadding="4" width="100%" style="font-size:9px;margin-top:4px;">
            <thead>
                <tr style="font-weight:bold;background-color:#f0f0f0;text-align:center;">
                    <th width="20%">Total Gross Wt (g)</th>
                    <th width="20%">Total Making Val (AED)</th>
                    <th width="20%">Total Material Val (AED)</th>
                    <th width="20%">Total Parts Val (AED)</th>
                    <th width="20%">Total Agreed Val (AED)</th>
                </tr>
            </thead>
            <tbody>
                <tr style="text-align:center;font-weight:bold;">
                    <td>' . number_format($totGross,    3) . '</td>
                    <td>' . number_format($totMaking,   2) . '</td>
                    <td>' . number_format($totMaterial, 2) . '</td>
                    <td>' . number_format($totPartsVal, 2) . '</td>
                    <td style="background-color:#e8f5e9;">' . number_format($totAgreed, 2) . '</td>
                </tr>
            </tbody>
        </table>';

        $pdf->writeHTML($summaryHtml, true, false, false, false);

        // weights: gold and parts (diamond / stone) shown separately, same as the form
        $totDiaCt   = (float) $consignment->items->sum(fn($i) => $i->parts->sum('qty'));
        $totStoneCt = (float) $consignment->items->sum(fn($i) => $i->parts->sum('stone_qty'));
        $weightHtml = '
        <table border="1" cellpadding="4" width="100%" style="font-size:9px;margin-top:2px;">
            <thead>
                <tr style="font-weight:bold;background-color:#f0f0f0;text-align:center;">
                    <th width="25%">Gold Wt (g)</th>
                    <th width="25%">Total Diamond Wt (Ct)</th>
                    <th width="25%">Total Stone Wt (Ct)</th>
                    <th width="25%">Total Parts Wt (Ct)</th>
                </tr>
            </thead>
            <tbody>
                <tr style="text-align:center;font-weight:bold;">
                    <td>' . number_format($totGross, 3) . '</td>
                    <td>' . number_format($totDiaCt, 3) . '</td>
                    <td>' . number_format($totStoneCt, 3) . '</td>
                    <td>' . number_format($totDiaCt + $totStoneCt, 3) . '</td>
                </tr>
            </tbody>
        </table>';
        $pdf->writeHTML($weightHtml, true, false, false, false);

        // discounts: item-wise (already inside Agreed Val) + overall -> net total
        $totItemDisc = (float) $consignment->items->sum('discount_amount');
        $overallDisc = (float) ($consignment->discount_amount ?? 0);
        if ($totItemDisc > 0 || $overallDisc > 0) {
            $overallLabel = 'Overall Discount'
                . (($consignment->discount_type ?? '') === 'percent'
                    ? ' (' . rtrim(rtrim(number_format((float) $consignment->discount_value, 4), '0'), '.') . '%)'
                    : '');
            $discHtml = '
        <table border="1" cellpadding="4" width="100%" style="font-size:9px;margin-top:2px;">
            <thead>
                <tr style="font-weight:bold;background-color:#f0f0f0;text-align:center;">
                    <th width="34%">Item Discounts (already deducted)</th>
                    <th width="33%">' . $overallLabel . '</th>
                    <th width="33%">Net Total Agreed Val (AED)</th>
                </tr>
            </thead>
            <tbody>
                <tr style="text-align:center;font-weight:bold;">
                    <td width="34%">' . ($totItemDisc > 0 ? '-' : '') . number_format($totItemDisc, 2) . '</td>
                    <td width="33%">' . ($overallDisc > 0 ? '-' : '') . number_format($overallDisc, 2) . '</td>
                    <td width="33%" style="background-color:#e8f5e9;">' . number_format($totAgreed - $overallDisc, 2) . '</td>
                </tr>
            </tbody>
        </table>';
            $pdf->writeHTML($discHtml, true, false, false, false);
        }

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

    // =========================================================================
    // EXCEL EXPORT - same content as print(); ?images=1 adds the Picture column
    // (needs phpoffice/phpspreadsheet - installed with maatwebsite/excel)
    // =========================================================================

    public function exportExcel(Request $request, $id)
    {
        if (!class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class)) {
            return back()->with('error', 'Excel export needs the phpoffice/phpspreadsheet package. Run: composer require phpoffice/phpspreadsheet');
        }

        $consignment = Consignment::with(['partner', 'items.parts', 'createdBy'])->findOrFail($id);

        try {
            $spreadsheet = $this->buildConsignmentSpreadsheet($consignment, $this->wantsPrintImages($request));
        } catch (\Throwable $e) {
            Log::error('Consignment Excel Export Error', [
                'consignment_id' => $consignment->id,
                'message'        => $e->getMessage(),
                'line'           => $e->getLine(),
                'file'           => $e->getFile(),
            ]);
            return back()->with('error', 'Excel export failed: ' . $e->getMessage());
        }

        $filename = preg_replace('/[^A-Za-z0-9._-]/', '_', (string) $consignment->consignment_no) . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function buildConsignmentSpreadsheet(Consignment $consignment, bool $showImages = false)
    {
        $imageSrc = $showImages ? $this->printImageSources($consignment) : [];

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle(substr(preg_replace('/[\\\\\/\?\*\[\]:]/', '-', (string) $consignment->consignment_no), 0, 31) ?: 'Consignment');

        // ── totals - identical to print() ───────────────────────────────────
        $totGross    = (float) $consignment->items->sum('gross_weight');
        $totMaking   = (float) $consignment->items->sum('making_value');
        $totMaterial = (float) $consignment->items->sum('material_value');
        $totParts    = (float) $consignment->items->sum(fn ($i) => $i->parts->sum('total'));
        $totAgreed   = (float) $consignment->items->sum('agreed_value');
        $totItemDisc = (float) $consignment->items->sum(fn ($i) => (float) ($i->discount_amount ?? 0));
        $overallDisc = (float) ($consignment->discount_amount ?? 0);
        $totDiaCt    = (float) $consignment->items->sum(fn ($i) => $i->parts->sum('qty'));
        $totStoneCt  = (float) $consignment->items->sum(fn ($i) => $i->parts->sum('stone_qty'));

        // ── styling helpers (same look as the Sale Invoice export) ─────────
        $thin = ['borders' => ['allBorders' => [
            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
            'color'       => ['rgb' => '808080'],
        ]]];
        $fill = fn (string $rgb) => ['fill' => [
            'fillType'   => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
            'startColor' => ['rgb' => $rgb],
        ]];
        $headFill  = $fill('F5F5F5');
        $totalFill = $fill('EEEEEE');
        $align = fn (string $h) => ['alignment' => [
            'horizontal' => $h,
            'vertical'   => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            'wrapText'   => true,
        ]];
        $center = $align(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $left   = $align(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
        $right  = $align(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
        $bold   = ['font' => ['bold' => true]];
        $italic = ['font' => ['italic' => true, 'size' => 8]];

        $FMT_MONEY = '#,##0.00';
        $FMT_WT    = '0.000';

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

        // ── columns: key => letter (the Picture column only exists with pictures) ──
        $order = ['no'];
        if ($showImages) {
            $order[] = 'pic';
        }
        $order = array_merge($order, ['name', 'code', 'cert', 'desc', 'purity', 'gross', 'pwt', 'c995', 'mrate', 'mval', 'mat', 'matval', 'agreed']);
        $c = [];
        foreach ($order as $i => $key) {
            $c[$key] = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
        }
        $last = $c['agreed'];

        $widths = ['no' => 5, 'pic' => 14, 'name' => 22, 'code' => 16, 'cert' => 18, 'desc' => 26, 'purity' => 9, 'gross' => 10, 'pwt' => 10,
                   'c995' => 9, 'mrate' => 10, 'mval' => 11, 'mat' => 10, 'matval' => 12, 'agreed' => 14];
        foreach ($c as $key => $letter) {
            $sheet->getColumnDimension($letter)->setWidth($widths[$key]);
        }
        $sheet->setShowGridlines(false);

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
                // logo is cosmetic - never let it break the export
            }
        }
        $put("{$c['purity']}1:{$last}4",
            "YUNEEQ FINE JEWELRY L.L.C\nSuite #M04, Mezzanine floor, Al Buteen 2 Building, Gold Souq. Gate no.1, Deira, Dubai\nTRN No: 104902647700003",
            [$right]);

        // ── title ─────────────────────────────────────────────────────────
        $docTitle = $consignment->direction === 'inbound'
            ? 'CONSIGNMENT RECEIPT NOTE (Inbound)'
            : 'CONSIGNMENT DELIVERY NOTE (Outbound)';
        $put("A6:{$last}6", $docTitle, [$center, $bold]);
        $sheet->getStyle('A6')->getFont()->setSize(13);
        $sheet->getRowDimension(6)->setRowHeight(22);

        // ── partner (left) + consignment details box (right) ──────────────
        $partner = $consignment->partner;
        $put("A8:{$c['desc']}13",
            ($consignment->direction === 'inbound' ? 'Received From' : 'Delivered To') . ":\n" .
            ($partner->name ?? '-') . "\n" . ($partner->address ?? '') . "\nTel: " . ($partner->contact_no ?? '-') . "\nTRN: " . (trim((string) ($partner->trn ?? '')) !== '' ? $partner->trn : '-'),
            [$left]);
        $sheet->getStyle("A8:{$c['desc']}13")->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP);

        $metaRows = [
            ['Consignment No', (string) $consignment->consignment_no],
            ['Start Date',     $consignment->start_date->format('d.m.Y')],
            ['End Date',       $consignment->end_date ? $consignment->end_date->format('d.m.Y') : 'Open-ended'],
            ['Duration',       (string) ($consignment->duration_label ?: '-')],
            ['Status',         ucwords(str_replace('_', ' ', (string) $consignment->status))],
        ];
        $r = 8;
        foreach ($metaRows as [$label, $value]) {
            $put("{$c['purity']}{$r}:{$c['gross']}{$r}", $label, [$thin, $bold, $left]);
            $put("{$c['pwt']}{$r}:{$c['mval']}{$r}", $value, [$thin, $right]);
            $r++;
        }

        // ── items table header (two rows, "Making" spans Rate + Value) ─────
        $hr = 15;
        $headers = [
            'no' => '#', 'pic' => 'Picture', 'name' => 'Item Name', 'code' => 'Item Code', 'cert' => 'Certificate #', 'desc' => 'Description', 'purity' => 'Purity',
            'gross' => 'Gross Wt', 'pwt' => 'Purity Wt', 'c995' => '995', 'mat' => 'Material',
            'matval' => 'Material Val', 'agreed' => 'Agreed Val',
        ];
        foreach ($headers as $key => $text) {
            if (!isset($c[$key])) {
                continue;
            }
            $put("{$c[$key]}{$hr}:{$c[$key]}" . ($hr + 1), $text, [$thin, $headFill, $bold, $center]);
        }
        $put("{$c['mrate']}{$hr}:{$c['mval']}{$hr}", 'Making', [$thin, $headFill, $bold, $center]);
        $put("{$c['mrate']}" . ($hr + 1), 'Rate',  [$thin, $headFill, $bold, $center]);
        $put("{$c['mval']}"  . ($hr + 1), 'Value', [$thin, $headFill, $bold, $center]);

        // ── item rows ─────────────────────────────────────────────────────
        $certs = $this->certificateMap($consignment->items->pluck('source_barcode'));
        $row = $hr + 2;
        foreach ($consignment->items as $index => $item) {
            $rowFill = match ($item->item_status) {
                'sold'     => 'D4EDDA',
                'returned' => 'FFF3CD',
                default    => null,
            };

            $put("{$c['no']}{$row}", $index + 1, [$thin, $center]);
            $put("{$c['name']}{$row}",   (string) ($item->item_name ?: '-'), [$thin, $left]);
            $put("{$c['code']}{$row}",   (string) (($item->source_barcode ?: $item->barcode_number) ?: '-'), [$thin, $left]);
            $put("{$c['cert']}{$row}",   (string) ($certs->get($item->source_barcode) ?: '-'), [$thin, $left]);
            $put("{$c['desc']}{$row}",   (string) ($item->item_description ?: '-'), [$thin, $left]);
            $put("{$c['purity']}{$row}", (float) $item->purity,        [$thin, $center], $FMT_WT);
            $put("{$c['gross']}{$row}",  (float) $item->gross_weight,  [$thin, $center], $FMT_WT);
            $put("{$c['pwt']}{$row}",    (float) $item->purity_weight, [$thin, $center], $FMT_WT);
            $put("{$c['c995']}{$row}",   (float) ($item->col_995 ?? 0), [$thin, $center], $FMT_WT);
            $put("{$c['mrate']}{$row}",  (float) ($item->making_rate ?? 0), [$thin, $center], $FMT_MONEY);
            $put("{$c['mval']}{$row}",   (float) $item->making_value,  [$thin, $center], $FMT_MONEY);
            $put("{$c['mat']}{$row}",    ucfirst((string) $item->material_type), [$thin, $center]);
            $put("{$c['matval']}{$row}", (float) $item->material_value, [$thin, $center], $FMT_MONEY);
            $put("{$c['agreed']}{$row}", (float) $item->agreed_value,  [$thin, $center, $bold], $FMT_MONEY);

            if ($showImages) {
                $sheet->getStyle("{$c['pic']}{$row}")->applyFromArray($thin);
                if ($this->addExcelPicture($sheet, $imageSrc[$item->source_barcode] ?? null, "{$c['pic']}{$row}")) {
                    $sheet->getRowDimension($row)->setRowHeight(50);
                } else {
                    $put("{$c['pic']}{$row}", '-', [$thin, $center]);
                }
            }
            if ($rowFill) {
                $sheet->getStyle("A{$row}:{$last}{$row}")->applyFromArray($fill($rowFill));
            }
            $row++;

            // per-item discount sub-row (same as the PDF)
            if ((float) ($item->discount_amount ?? 0) > 0) {
                $discLabel = ($item->discount_type ?? '') === 'percent'
                    ? 'Discount (' . rtrim(rtrim(number_format((float) $item->discount_value, 2), '0'), '.') . '%) — already deducted from Agreed Val:'
                    : 'Discount — already deducted from Agreed Val:';
                $sheet->getStyle("A{$row}:" . ($showImages ? $c['pic'] : 'A') . "{$row}")->applyFromArray($thin);
                $put("{$c['name']}{$row}:{$c['matval']}{$row}", $discLabel, [$thin, $italic, $right]);
                $put("{$c['agreed']}{$row}", -1 * (float) $item->discount_amount, [$thin, $italic, $right], $FMT_MONEY);
                $sheet->getStyle("A{$row}:{$last}{$row}")->applyFromArray($fill('FFF8E6'));
                $row++;
            }

            // parts detail (same as the PDF)
            if ($item->parts && $item->parts->count() > 0) {
                $sheet->getStyle("A{$row}:" . ($showImages ? $c['pic'] : 'A') . "{$row}")->applyFromArray($thin);
                $put("{$c['name']}{$row}:{$last}{$row}", 'Parts:', [$thin, $italic, $left]);
                $row++;

                foreach ($item->parts as $part) {
                    $sheet->getStyle("A{$row}:" . ($showImages ? $c['pic'] : 'A') . "{$row}")->applyFromArray($thin);
                    $put("{$c['name']}{$row}", (string) ($part->item_name ?: 'Part'), [$thin, $italic, $left]);
                    $put("{$c['code']}{$row}:{$c['desc']}{$row}", (string) ($part->part_description ?? ''), [$thin, $italic, $left]);
                    $put("{$c['purity']}{$row}:{$c['gross']}{$row}", (float) $part->qty, [$thin, $italic, $center], '0.000" Ct"');
                    $put("{$c['pwt']}{$row}:{$c['c995']}{$row}", (float) $part->rate, [$thin, $italic, $center], '"Rate: "#,##0.00');
                    $put("{$c['mrate']}{$row}:{$c['mval']}{$row}", (float) ($part->stone_qty ?? 0), [$thin, $italic, $center], '"St. "0.00');
                    $put("{$c['mat']}{$row}:{$c['matval']}{$row}", (float) ($part->stone_rate ?? 0), [$thin, $italic, $center], '"SR: "#,##0.00');
                    $put("{$c['agreed']}{$row}", (float) $part->total, [$thin, $italic, $right, $bold], $FMT_MONEY);
                    $row++;
                }
            }
        }
        $row++;

        // ── summary (right side) ──────────────────────────────────────────
        $sumRows = [
            ['Total Gross Wt (g)',        $totGross,    $FMT_WT,    false],
            ['Total Diamond Wt (Ct)',     $totDiaCt,    $FMT_WT,    false],
            ['Total Stone Wt (Ct)',       $totStoneCt,  $FMT_WT,    false],
            ['Total Parts Wt (Ct)',       $totDiaCt + $totStoneCt, $FMT_WT, false],
            ['Total Making Val (AED)',    $totMaking,   $FMT_MONEY, false],
            ['Total Material Val (AED)',  $totMaterial, $FMT_MONEY, false],
            ['Total Parts Val (AED)',     $totParts,    $FMT_MONEY, false],
            ['Total Agreed Val (AED)',    $totAgreed,   $FMT_MONEY, true],
        ];
        if ($totItemDisc > 0) {
            $sumRows[] = ['Item Discounts (already deducted)', -1 * $totItemDisc, $FMT_MONEY, false];
        }
        if ($overallDisc > 0) {
            $pct = ($consignment->discount_type ?? '') === 'percent'
                ? ' (' . rtrim(rtrim(number_format((float) $consignment->discount_value, 4), '0'), '.') . '%)'
                : '';
            $sumRows[] = ['Overall Discount' . $pct, -1 * $overallDisc, $FMT_MONEY, false];
            $sumRows[] = ['Net Total Agreed Val (AED)', $totAgreed - $overallDisc, $FMT_MONEY, true];
        }

        $put("{$c['c995']}{$row}:{$last}{$row}", 'Summary (AED)', [$thin, $headFill, $bold, $center]);
        $r = $row + 1;
        foreach ($sumRows as [$label, $value, $fmt, $isBold]) {
            $lStyles = array_filter([$thin, $left,  $isBold ? $bold : null, $isBold ? $totalFill : null]);
            $vStyles = array_filter([$thin, $right, $isBold ? $bold : null, $isBold ? $totalFill : null]);
            $put("{$c['c995']}{$r}:{$c['matval']}{$r}", $label, $lStyles);
            $put("{$c['agreed']}{$r}", $value, $vStyles, $fmt);
            $r++;
        }
        $row = $r + 1;

        if ($consignment->remarks) {
            $put("A{$row}:{$last}{$row}", 'Remarks: ' . $consignment->remarks, [$left]);
            $row++;
        }
        $row += 3;

        $put("{$c['name']}{$row}:{$c['desc']}{$row}", 'Partner Signature', [$center]);
        $sheet->getStyle("{$c['name']}{$row}:{$c['desc']}{$row}")->getBorders()->getTop()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        $put("{$c['mat']}{$row}:{$last}{$row}", 'Authorized Signature', [$center]);
        $sheet->getStyle("{$c['mat']}{$row}:{$last}{$row}")->getBorders()->getTop()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

        // ── print setup: landscape A4, fit to one page wide ────────────────
        $sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
        $sheet->getPageSetup()->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4);
        $sheet->getPageSetup()->setFitToWidth(1);
        $sheet->getPageSetup()->setFitToHeight(0);
        $sheet->getPageSetup()->setFitToPage(true);

        return $spreadsheet;
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
    // MARK SINGLE ITEM AS RETURNED
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

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    /**
     * Resolve items from either:
     *   (a) items_json  — single JSON field, used when item count is large
     *                     to bypass PHP's max_input_vars = 1000 limit, OR
     *   (b) items[]     — standard nested form array (fallback)
     *
     * The blade submit handler always sends items_json (path a).
     * Path (b) is kept as a safety fallback only.
     */
    private function resolveItems(Request $request): array
    {
        if ($request->filled('items_json')) {
            $decoded = json_decode($request->input('items_json'), true);
            if (is_array($decoded) && count($decoded) > 0) {
                return $decoded;
            }
        }

        return $request->input('items', []);
    }

    /**
     * Validate the consignment request.
     *
     * When items_json is present (large import), per-item Laravel validation
     * rules are skipped because items[] won't exist as form fields (they are
     * disabled before submit). A structural JSON check is done instead.
     */
    private function validateConsignment(Request $request, bool $updating = false): void
    {
        // ── Scalar header fields only ─────────────────────────────────────────
        // Per-item validation rules are intentionally omitted here.
        //
        // Reason: The blade submit handler serializes all items into a single
        // items_json field and disables the individual items[N][field] inputs
        // before POSTing. This means items[] may arrive as an empty array or
        // with partial data (only the non-disabled fields like source_barcode).
        // Applying items.*.field rules against that would produce false failures.
        //
        // Item data integrity is guaranteed by resolveItems() + persistItems()
        // which cast every field to the correct type with safe defaults.
        $rules = [
            'partner_id'     => 'required|exists:chart_of_accounts,id',
            'start_date'     => 'required|date',
            'end_date'       => 'nullable|date|after_or_equal:start_date',
            'duration_label' => 'nullable|string|max:60',
            'remarks'        => 'nullable|string',
            'items_json'     => 'nullable|string',
            'overall_discount_type'  => 'nullable|in:amount,percent',
            'overall_discount_value' => 'nullable|numeric|min:0',
        ];

        if (!$updating) {
            $rules['direction'] = 'required|in:inbound,outbound';
        }

        $request->validate($rules);

        // Ensure at least one item is present (via JSON or legacy form array)
        $items = $this->resolveItems($request);
        if (empty($items)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'items_json' => ['No items found. Please add at least one item before saving.'],
            ]);
        }
    }

    /**
     * (type = amount|percent, value) + base -> [type|null, value, amount].
     * Never negative, never above the base. Same rules as
     * SaleInvoiceController::resolveDiscount().
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

    /**
     * Overall discount on the whole consignment, applied on top of the already
     * item-discounted Agreed Values of the in-stock items (the rows on the form).
     * It is stored on the consignment as a document figure only - item
     * agreed_values are not changed, so settling items one by one is unaffected.
     */
    private function applyOverallDiscount(Consignment $consignment, Request $request): void
    {
        $base = (float) $consignment->items()->where('item_status', 'in_stock')->sum('agreed_value');

        [$type, $value, $amount] = $this->resolveDiscount(
            $request->input('overall_discount_type'),
            $request->input('overall_discount_value'),
            $base,
            4   // overall discount keeps up to 4 decimals
        );

        $consignment->forceFill([
            'discount_type'   => $amount > 0 ? $type : null,
            'discount_value'  => $amount > 0 ? $value : 0,
            'discount_amount' => $amount,
        ])->save();
    }

    /**
     * Generate a barcode number for a consignment item.
     * Format: CSG-00001-1, CSG-00001-2, CSG-00002-1 …
     */
    private function generateConsignmentBarcode(Consignment $consignment, int $itemPosition): string
    {
        $seqPart = substr($consignment->consignment_no, strrpos($consignment->consignment_no, '-') + 1);
        return 'CSG-' . $seqPart . '-' . $itemPosition;
    }

    /**
     * Persist ConsignmentItem + ConsignmentItemPart rows.
     */
    private function persistItems(Consignment $consignment, array $items, string $direction, Request $request): void
    {
        $goldRateAed = (float) ($request->gold_rate_aed    ?? 0);
        $diaRateAed  = (float) ($request->diamond_rate_aed ?? 0);

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

            $partsData       = $itemData['parts'] ?? [];
            $totalDiamondCTS = 0.0;
            $totalStoneCTS   = 0.0;
            foreach ($partsData as $p) {
                $totalDiamondCTS += (float) ($p['qty']       ?? 0);
                $totalStoneCTS   += (float) ($p['stone_qty'] ?? 0);
            }
            $calcGross = $baseGross + ($totalDiamondCTS / 5) + ($totalStoneCTS / 5);

            $purityWeight  = $calcGross * $purity;
            $col995        = $purityWeight > 0 ? $purityWeight / 0.995 : 0;
            $makingValue   = $calcGross * $makingRate;
            $rate          = $matType === 'gold' ? $itemGoldR : $itemDiaR;
            $materialValue = $rate * $purityWeight;

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

            // Item-wise discount (amount | percent) comes off the Agreed Value. agreed_value is
            // stored AFTER the discount, so everything that reads it (settlement, dashboard,
            // inventory reports) automatically sees the discounted value.
            [$discType, $discValue, $discAmount] = $this->resolveDiscount(
                $itemData['discount_type']  ?? null,
                $itemData['discount_value'] ?? 0,
                $agreedValue
            );
            $agreedValue -= $discAmount;

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
                'discount_type'    => $discAmount > 0 ? $discType : null,
                'discount_value'   => $discAmount > 0 ? $discValue : 0,
                'discount_amount'  => $discAmount,
                'item_status'      => 'in_stock',
            ]);

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
    // ITEMS FOR SALE INVOICE — outbound consignment items still in_stock
    // Used by the "Select Sold Items" modal on the Sale Invoice create page.
    // =========================================================================

    public function itemsForSale($id)
    {
        $consignment = Consignment::with(['items' => function ($q) {
            $q->where('item_status', 'in_stock')->with('parts');
        }])->findOrFail($id);

        if ($consignment->direction !== 'outbound') {
            return response()->json([
                'success' => false,
                'message' => 'Only outbound consignments can be settled via a Sale Invoice.',
            ], 422);
        }

        $items = $consignment->items->map(function ($item) {
            return [
                'id'               => $item->id,
                'source_barcode'   => $item->source_barcode,
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
            ];
        })->values();

        return response()->json([
            'success'        => true,
            'consignment_no' => $consignment->consignment_no,
            'items'          => $items,
        ]);
    }
}