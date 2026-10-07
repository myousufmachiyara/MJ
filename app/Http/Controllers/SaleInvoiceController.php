<?php

namespace App\Http\Controllers;

use App\Models\SaleInvoice;
use App\Http\Controllers\ConsignmentController;
use App\Models\SaleInvoiceItem;
use App\Models\SaleInvoiceItemPart;
use App\Models\PurchaseInvoiceItem;
use App\Models\Product;
use App\Models\Voucher;
use App\Models\AccountingEntry;
use App\Models\Purity;
use App\Models\ChartOfAccounts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\myPDF;
use Carbon\Carbon;

class SaleInvoiceController extends Controller
{
    // =========================================================================
    // INDEX
    // =========================================================================

    public function index()
    {
        $invoices = SaleInvoice::with('customer', 'attachments')->get();
        return view('sales.index', compact('invoices'));
    }

    // =========================================================================
    // CREATE
    // =========================================================================

    public function create()
    {
        $customers = ChartOfAccounts::where('account_type', 'customer')->get();
        $banks     = ChartOfAccounts::whereIn('account_type', ['bank', 'cash'])->get();
        $products  = Product::with('measurementUnit')->get();
        $purities  = Purity::all();

        $outboundConsignments = \App\Models\Consignment::where('direction', 'outbound')
            ->whereIn('status', ['active', 'partially_settled'])
            ->with('partner')
            ->orderByDesc('id')
            ->get();

        return view('sales.create', compact(
            'products', 'customers', 'banks', 'purities', 'outboundConsignments'
        ));
    }

    // =========================================================================
    // POS — barcode-driven counter-sale screen (Sale Invoice, alternate UI)
    //
    // This is NOT a separate sales system — it still submits to the exact
    // same store() action as sales.create. It IS its own permission module
    // though ('pos.index', seeded in DatabaseSeeder.php) so it can be
    // granted independently of the full Sale Invoice screens — see
    // routes/web.php (sale_invoices.pos / sale_invoices.pos_scan routes)
    // and resources/views/sales/pos.blade.php. Checkout itself still goes
    // through the store route's own unchanged 'sale_invoices.create' gate,
    // so a POS user needs both permissions: pos.index to open the screen,
    // sale_invoices.create to save the invoice it produces. This screen
    // only needs customers/banks — no products/purities/consignments,
    // since POS never shows costing fields and always looks up purchased
    // items by barcode via posScan().
    // =========================================================================

    public function pos()
    {
        $customers = ChartOfAccounts::where('account_type', 'customer')->get();
        $banks     = ChartOfAccounts::whereIn('account_type', ['bank', 'cash'])->get();

        return view('sales.pos', compact('customers', 'banks'));
    }

    /**
     * POS barcode lookup — Ajax endpoint.
     *
     * Deliberately separate from scanBarcode() (used by the full Sale
     * Invoice create/edit screens) rather than reusing it, because the two
     * have different sources of truth and different response shapes:
     *   - scanBarcode() searches sale items, consignment items AND purchase
     *     items, and returns full costing fields (making_rate/vat_percent/
     *     parts/etc.) for the detailed invoice form.
     *   - posScan() searches ONLY PurchaseInvoiceItem — "Purchase Items ARE
     *     our Products," there is no separate Product Master — and returns
     *     only the non-costing fields the POS screen is allowed to show
     *     (Item, Code, Barcode, Category, Selling Price, Total/Gold/Diamond/
     *     Stone Weight), plus two POS-specific guards neither scanBarcode()
     *     nor the full Sale Invoice screen enforce today: refusing an item
     *     with no Selling Price set, and refusing an item that has already
     *     been sold on another Sale Invoice. (scanBarcode() intentionally
     *     keeps its existing behavior — unchanged — since altering it is
     *     out of scope for this feature.)
     *
     * Uses the same short numeric-surrogate vs. full barcode_number
     * distinction as scanBarcode() (see that method's comment for why the
     * printed label encodes a short numeric surrogate rather than the full
     * barcode_number).
     */
    public function posScan(Request $request)
    {
        $barcode = trim((string) $request->get('barcode'));

        if (!$barcode) {
            return response()->json(['success' => false, 'message' => 'No barcode provided.'], 422);
        }

        // 'parts' eager-loaded because getDiamondTotalCtAttribute()/
        // getStoneTotalCtAttribute() (PurchaseInvoiceItem) sum over it.
        $purchaseItem = ctype_digit($barcode)
            ? PurchaseInvoiceItem::with(['category', 'subcategory', 'parts'])->find((int) $barcode)
            : PurchaseInvoiceItem::with(['category', 'subcategory', 'parts'])
                ->where('barcode_number', $barcode)
                ->latest()
                ->first();

        if (!$purchaseItem) {
            return response()->json([
                'success' => false,
                'message' => 'Barcode "' . $barcode . '" not found.',
            ], 404);
        }

        // FEATURE (Sale Invoice POS): a purchased item that already appears
        // on a Sale Invoice has already been sold and is no longer in
        // stock. Reuses the existing barcode_number field/relationship —
        // no new stock table or stock-calculation logic.
        $alreadySold = SaleInvoiceItem::where('barcode_number', $purchaseItem->barcode_number)->exists();
        if ($alreadySold) {
            return response()->json([
                'success' => false,
                'message' => 'Item "' . $purchaseItem->barcode_number . '" has already been sold.',
            ], 409);
        }

        // FIX (POS reads BOTH prices): an item carries two independent,
        // manually-set prices — selling_price (AED) and selling_price_usd
        // (USD); nothing is converted between them. POS now returns BOTH so
        // the screen can show both side by side and bill in whichever
        // currency is selected. The item is only refused when NEITHER price
        // is set (never silently sold at 0); a missing price in just the
        // currency currently selected is flagged by the POS screen itself,
        // which lets the cashier switch currency instead of losing the scan.
        $priceAed = $purchaseItem->selling_price;
        $priceUsd = $purchaseItem->selling_price_usd;

        if ($priceAed === null && $priceUsd === null) {
            return response()->json([
                'success' => false,
                'message' => 'Selling price is not set for this item (neither AED nor USD).',
            ], 422);
        }

        return response()->json([
            'success'           => true,
            'barcode_number'    => $purchaseItem->barcode_number,
            'item_name'         => $purchaseItem->item_name,
            'category'          => $purchaseItem->subcategory->name ?? $purchaseItem->category->name ?? null,
            'material_type'     => $purchaseItem->material_type,
            'gross_weight'      => $purchaseItem->gross_weight,
            'net_weight'        => $purchaseItem->net_weight,
            'diamond_total_ct'  => $purchaseItem->diamond_total_ct,
            'stone_total_ct'    => $purchaseItem->stone_total_ct,
            'selling_price_aed' => $priceAed,
            'selling_price_usd' => $priceUsd,
        ]);
    }

    // =========================================================================
    // BARCODE SCAN — Ajax endpoint
    // =========================================================================

    public function scanBarcode(Request $request)
    {
        $barcode = trim($request->get('barcode'));

        if (!$barcode) {
            return response()->json(['success' => false, 'message' => 'No barcode provided.'], 422);
        }

        // FIX (unscannable long barcodes): printed purchase labels
        // (purchase/barcodes.blade.php) now encode a short, purely-numeric
        // surrogate in the barcode SYMBOL itself — the item's own id,
        // zero-padded to 6 digits (see
        // PurchaseInvoiceItem::getScanCodeAttribute()) — instead of the
        // full barcode_number text, because barcode_number can run
        // 10-14+ characters once a subcategory code is involved, which is
        // too dense to scan reliably at the label's fixed (12mm) barcode
        // width. barcode_number itself is completely unchanged everywhere
        // else (stored, displayed, on certificates) — this only decides
        // how a SCAN is looked up.
        //
        // Every barcode_number format this app generates — legacy
        // "MJ-…"/"MJT-…" and the newer "{SubcategoryCode}-00001" — always
        // contains a letter or a dash, so a scan that comes in as pure
        // digits can only ever be one of these short surrogate codes, and
        // can never collide with a manually-typed barcode_number. Typing a
        // real barcode_number by hand still works exactly as before, via
        // the lookups further down.
        if (ctype_digit($barcode)) {
            $purchaseItem = PurchaseInvoiceItem::with('parts')->find((int) $barcode);

            if ($purchaseItem) {
                return $this->purchaseItemScanResponse($purchaseItem);
            }

            return response()->json([
                'success' => false,
                'message' => 'Scanned label not recognised (item #' . $barcode . ' not found — it may have been deleted).',
            ], 404);
        }

        $saleItem = SaleInvoiceItem::with('parts')
            ->where('barcode_number', $barcode)
            ->latest()
            ->first();

        if ($saleItem) {
            return response()->json([
                'success'          => true,
                'source'           => 'sale',
                'barcode_number'   => $saleItem->barcode_number,
                'item_name'        => $saleItem->item_name,
                'item_description' => $saleItem->item_description,
                'purity'           => $saleItem->purity,
                'gross_weight'     => $saleItem->gross_weight,
                'making_rate'      => $saleItem->making_rate,
                'material_type'    => $saleItem->material_type,
                'vat_percent'      => $saleItem->vat_percent,
                'parts'            => $saleItem->parts->map(fn($p) => [
                    'item_name'        => $p->item_name,
                    'part_description' => $p->part_description,
                    'qty'              => $p->qty,
                    'rate'             => $p->rate,
                    'stone_qty'        => $p->stone_qty,
                    'stone_rate'       => $p->stone_rate,
                    'total'            => $p->total,
                ])->values()->toArray(),
            ]);
        }

        if (str_starts_with($barcode, 'CSG-')) {
            $consignmentItem = \App\Models\ConsignmentItem::with(['parts', 'consignment'])
                ->where('barcode_number', $barcode)
                ->where('item_status', 'in_stock')
                ->first();

            if ($consignmentItem) {
                return response()->json([
                    'success'          => true,
                    'source'           => 'consignment',
                    'consignment_no'   => $consignmentItem->consignment->consignment_no,
                    'barcode_number'   => $consignmentItem->barcode_number,
                    'item_name'        => $consignmentItem->item_name,
                    'item_description' => $consignmentItem->item_description,
                    'purity'           => $consignmentItem->purity,
                    'gross_weight'     => $consignmentItem->gross_weight,
                    'making_rate'      => $consignmentItem->making_rate,
                    'material_type'    => $consignmentItem->material_type,
                    'vat_percent'      => $consignmentItem->vat_percent,
                    'agreed_value'     => $consignmentItem->agreed_value,
                    'parts'            => $consignmentItem->parts->map(fn($p) => [
                        'item_name'        => $p->item_name,
                        'part_description' => $p->part_description,
                        'qty'              => $p->qty,
                        'rate'             => $p->rate,
                        'stone_qty'        => $p->stone_qty,
                        'stone_rate'       => $p->stone_rate,
                        'total'            => $p->total,
                    ])->values()->toArray(),
                ]);
            }
        }

        $purchaseItem = PurchaseInvoiceItem::with('parts')
            ->where('barcode_number', $barcode)
            ->latest()
            ->first();

        if ($purchaseItem) {
            return $this->purchaseItemScanResponse($purchaseItem);
        }

        return response()->json([
            'success' => false,
            'message' => 'Barcode "' . $barcode . '" not found in any record.',
        ], 404);
    }

    /**
     * Shared JSON shape for a purchase-item scan result. Used by both the
     * short numeric-surrogate fast path and the legacy barcode_number
     * match above, so the two lookup routes can never drift into
     * returning different fields for what is otherwise the same item.
     */
    private function purchaseItemScanResponse(PurchaseInvoiceItem $purchaseItem)
    {
        return response()->json([
            'success'          => true,
            'source'           => 'purchase',
            'barcode_number'   => $purchaseItem->barcode_number,
            'item_name'        => $purchaseItem->item_name,
            'item_description' => $purchaseItem->item_description,
            'purity'           => $purchaseItem->purity,
            'gross_weight'     => $purchaseItem->gross_weight,
            'making_rate'      => $purchaseItem->making_rate,
            'material_type'    => $purchaseItem->material_type,
            'vat_percent'      => $purchaseItem->vat_percent,
            'parts'            => $purchaseItem->parts->map(fn($p) => [
                'item_name'        => $p->item_name,
                'part_description' => $p->part_description,
                'qty'              => $p->qty,
                'rate'             => $p->rate,
                'stone_qty'        => $p->stone_qty,
                'stone_rate'       => $p->stone_rate,
                'total'            => $p->total,
            ])->values()->toArray(),
        ]);
    }

    // =========================================================================
    // STORE
    // =========================================================================

    public function store(Request $request)
    {
        $this->clearIrrelevantPaymentFields($request);
        $this->validateInvoice($request);

        // FIX (duplicate invoice_no race): a double-click Save, or a slow
        // request the browser silently retried, can fire two near-simultaneous
        // store() calls. Both read the same "last" invoice number before
        // either commits, so the loser's insert can hit the unique invoice_no
        // constraint even though generateInvoiceNo() uses lockForUpdate().
        // Retry the whole save with a freshly generated number a couple of
        // times before giving up, instead of surfacing a raw SQL error.
        $maxAttempts = 3;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
        try {
            DB::beginTransaction();

            $isTaxable = $request->boolean('is_taxable');
            $invoiceNo = $this->generateInvoiceNo($isTaxable);

            $invoice = SaleInvoice::create([
                'invoice_no'               => $invoiceNo,
                'is_taxable'               => $isTaxable,
                'customer_id'              => $request->customer_id,
                'consignment_id'           => $request->consignment_id ?: null,
                'invoice_date'             => $request->invoice_date,
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
                'invoice_vat_percent'      => 0,
                'invoice_vat_amount'       => 0,
                'grand_total'              => 0,
                'payment_method'           => $request->payment_method,
                'payment_term'             => $request->payment_term,
                'cash_amount_paid'         => $request->cash_amount_paid,
                // FIX (Making Charges Collected Now): persist what was actually
                // collected so edit() can prefill it. Previously this value only
                // ever lived in the request, was used once to build the voucher,
                // then discarded — so the edit blade always showed 0 regardless
                // of what was collected on a prior save.
                'making_amount_collected'  => $request->making_amount_paid ?? 0,
                'bank_name'                => $request->bank_name,
                'cheque_no'                => $request->cheque_no,
                'cheque_date'              => $request->cheque_date,
                'cheque_amount'            => $request->cheque_amount,
                'transfer_from_bank'       => $request->transfer_from_bank,
                'transfer_to_bank'         => $request->transfer_to_bank,
                'account_title'            => $request->account_title,
                'account_no'               => $request->account_no,
                'transaction_id'           => $request->transaction_id,
                'transfer_date'            => $request->transfer_date,
                'transfer_amount'          => $request->transfer_amount,
                'material_received_by'     => $request->material_received_by,
                'material_given_by'        => $request->material_given_by,
                'created_by'               => auth()->id(),
            ]);

            [$totals] = $this->createItems($invoice, $request->items, $request);

            // FEATURE (discount): item-level discounts are already inside each
            // item_total (see createItems()); this applies the optional
            // invoice-level discount on top and returns the matching
            // (discount-scaled) accounting totals.
            [$calculatedNet, $totals] = $this->applyInvoiceLevelDiscount($invoice, $totals, $request);
            $calculatedNetAed = $request->currency === 'USD'
                ? round($calculatedNet * ($request->exchange_rate ?? 1), 2)
                : $calculatedNet;

            $invoice->update([
                'net_amount'     => round($calculatedNet, 2),
                'net_amount_aed' => $calculatedNetAed,
            ]);

            $invoiceVatPct    = $isTaxable ? (float) ($request->invoice_vat_percent ?? 0) : 0.0;
            $invoiceVatAmount = round($calculatedNetAed * $invoiceVatPct / 100, 2);
            $grandTotal       = round($calculatedNetAed + $invoiceVatAmount, 2);

            $invoice->update([
                'invoice_vat_percent' => $invoiceVatPct,
                'invoice_vat_amount'  => $invoiceVatAmount,
                'grand_total'         => $grandTotal,
            ]);

            $this->storeAttachments($request, $invoice);
            $this->createSaleAccountingEntries($invoice, $totals, $request);
            ConsignmentController::settleItems($invoice->load('items'));

            DB::commit();

            return redirect()
                ->route('sale_invoices.index')
                ->with('success', 'Invoice #' . $invoiceNo . ' saved successfully.');

        } catch (\Throwable $e) {
            DB::rollBack();

            if ($this->isDuplicateInvoiceNoError($e) && $attempt < $maxAttempts) {
                continue;
            }

            Log::error('Sale Invoice Store Error', [
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

    /**
     * True when $e is a MySQL duplicate-entry error (1062) on the invoice_no
     * unique key specifically — as opposed to any other constraint violation
     * (which should still fail immediately rather than retry).
     */
    private function isDuplicateInvoiceNoError(\Throwable $e): bool
    {
        return $e instanceof \Illuminate\Database\QueryException
            && (int) ($e->errorInfo[1] ?? 0) === 1062
            && str_contains($e->getMessage(), 'invoice_no');
    }

    // =========================================================================
    // EDIT
    // =========================================================================

    public function edit($id)
    {
        $saleInvoice = SaleInvoice::with(['items.parts', 'attachments'])->findOrFail($id);
        $purities    = Purity::all();
        $customers   = ChartOfAccounts::where('account_type', 'customer')->get();
        $banks       = ChartOfAccounts::whereIn('account_type', ['bank', 'cash'])->get();
        $products    = Product::with('measurementUnit')->get();

        $goldAedOunce    = ($saleInvoice->gold_rate_aed    ?? 0) * 31.1035;
        $diamondAedOunce = ($saleInvoice->diamond_rate_aed ?? 0) * 31.1035;

        $outboundConsignments = \App\Models\Consignment::where('direction', 'outbound')
            ->whereIn('status', ['active', 'partially_settled'])
            ->with('partner')
            ->orderByDesc('id')
            ->get();

        $itemsData = $saleInvoice->items->map(function ($item) {
            return [
                'item_name'        => $item->item_name,
                'barcode_number'   => $item->barcode_number,
                'is_printed'       => $item->is_printed,
                'product_id'       => $item->product_id,
                'item_description' => $item->item_description,
                'purity'           => $item->purity,
                'gross_weight'     => $item->gross_weight,
                'making_rate'      => $item->making_rate,
                'material_type'    => $item->material_type,
                // FEATURE (discount): prefill what was entered (type + value).
                // The invoice-level discount is read straight off $saleInvoice
                // in the view: discount_type / discount_value / discount_amount.
                'discount_type'    => $item->discount_type ?? null,
                'discount_value'   => (float) ($item->discount_value ?? 0),
                'discount_amount'  => (float) ($item->discount_amount ?? 0),
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

        return view('sales.edit', compact(
            'saleInvoice', 'customers', 'banks', 'products',
            'itemsData', 'goldAedOunce', 'diamondAedOunce', 'purities',
            'outboundConsignments'
        ));
    }

    // =========================================================================
    // UPDATE
    // =========================================================================

    public function update(Request $request, $id)
    {
        $invoice = SaleInvoice::findOrFail($id);

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

        // FIX (duplicate invoice_no race): same guard as store() — only
        // reachable here when the invoice's Tax/Non-Tax type changed on this
        // save (the only branch below that generates a brand new invoice_no),
        // but kept for consistency.
        $maxAttempts = 3;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
        try {
            DB::beginTransaction();

            $newIsTaxable = $request->boolean('is_taxable');
            $invoiceNo    = $invoice->invoice_no;

            if ($newIsTaxable !== (bool) $invoice->is_taxable) {
                $invoiceNo = $this->generateInvoiceNo($newIsTaxable);
            }

            $invoice->update([
                'invoice_no'               => $invoiceNo,
                'is_taxable'               => $newIsTaxable,
                'customer_id'              => $request->customer_id,
                'consignment_id'           => $request->consignment_id ?: null,
                'invoice_date'             => $request->invoice_date,
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
                'payment_method'           => $request->payment_method,
                'payment_term'             => $request->payment_term,
                'cash_amount_paid'         => $request->cash_amount_paid,
                // FIX (Making Charges Collected Now): same persistence as store().
                // Whatever the user typed into "Making Charges Collected Now" on
                // THIS save is what edit() will prefill next time — so re-opening
                // this invoice always reflects what was actually collected last,
                // instead of resetting to 0 and risking double-counting the
                // outstanding balance on the next accounting-entry regeneration.
                'making_amount_collected'  => $request->making_amount_paid ?? 0,
                'bank_name'                => $request->bank_name,
                'cheque_no'                => $request->cheque_no,
                'cheque_date'              => $request->cheque_date,
                'cheque_amount'            => $request->cheque_amount,
                'transfer_from_bank'       => $request->transfer_from_bank,
                'transfer_to_bank'         => $request->transfer_to_bank,
                'account_title'            => $request->account_title,
                'account_no'               => $request->account_no,
                'transaction_id'           => $request->transaction_id,
                'transfer_date'            => $request->transfer_date,
                'transfer_amount'          => $request->transfer_amount,
                'material_received_by'     => $request->material_received_by,
                'material_given_by'        => $request->material_given_by,
            ]);

            foreach ($invoice->items as $oldItem) {
                $oldItem->parts()->delete();
            }
            $invoice->items()->delete();

            [$totals] = $this->createItems($invoice, $request->items, $request, preservePrinted: true);

            // FEATURE (discount): item-level discounts are already inside each
            // item_total (see createItems()); this applies the optional
            // invoice-level discount on top and returns the matching
            // (discount-scaled) accounting totals.
            [$calculatedNet, $totals] = $this->applyInvoiceLevelDiscount($invoice, $totals, $request);
            $calculatedNetAed = $request->currency === 'USD'
                ? round($calculatedNet * ($request->exchange_rate ?? 1), 2)
                : $calculatedNet;

            $invoice->update([
                'net_amount'     => round($calculatedNet, 2),
                'net_amount_aed' => $calculatedNetAed,
            ]);

            $invoiceVatPct    = $newIsTaxable ? (float) ($request->invoice_vat_percent ?? 0) : 0.0;
            $invoiceVatAmount = round($calculatedNetAed * $invoiceVatPct / 100, 2);
            $grandTotal       = round($calculatedNetAed + $invoiceVatAmount, 2);

            $invoice->update([
                'invoice_vat_percent' => $invoiceVatPct,
                'invoice_vat_amount'  => $invoiceVatAmount,
                'grand_total'         => $grandTotal,
            ]);

            $this->storeAttachments($request, $invoice);

            $oldVoucher = Voucher::where('reference_type', SaleInvoice::class)
                ->where('reference_id', $invoice->id)
                ->first();
            if ($oldVoucher) {
                AccountingEntry::where('voucher_id', $oldVoucher->id)->delete();
                $oldVoucher->delete();
            }

            $this->createSaleAccountingEntries($invoice, $totals, $request);
            ConsignmentController::settleItems($invoice->load('items'));

            DB::commit();

            return redirect()
                ->route('sale_invoices.index')
                ->with('success', 'Invoice #' . $invoice->invoice_no . ' updated successfully.');

        } catch (\Throwable $e) {
            DB::rollBack();

            if ($this->isDuplicateInvoiceNoError($e) && $attempt < $maxAttempts) {
                continue;
            }

            Log::error('Sale Invoice Update Error', [
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

    // =========================================================================
    // PRINT — detailed B2B invoice (unchanged)
    // =========================================================================

    public function print($id)
    {
        $invoice = SaleInvoice::with([
            'customer',
            'items',
            'items.product.measurementUnit',
            'items.parts',
            'items.parts.product.measurementUnit',
            'bank',
            'transferBank',
            'vouchers.entries.account',
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
        // Material-payment invoices: the discount comes off the currency
        // portion only (the metal handed over is by weight) — see createItems().
        if (str_contains((string) $invoice->payment_method, 'material')) {
            $totalCurrencyPayable -= $totalDiscount;
        }

        $pdf = new myPDF();
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetCreator('Your App');
        $pdf->SetTitle($invoice->invoice_no);
        $pdf->SetMargins(10, 10, 10);
        $pdf->setCellPadding(1.2);

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

        $title = $invoice->is_taxable ? 'TAX INVOICE (SALE)' : 'SALE INVOICE';
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
                    TRN: ' . ($invoice->customer->trn ?? '-') . '<br>
                </td>
                <td width="50%">
                    <table border="1" cellpadding="3" width="100%">
                        <tr><td width="45%"><b>Date</b></td><td width="55%">' . Carbon::parse($invoice->invoice_date)->format('d.m.Y') . '</td></tr>
                        <tr><td><b>Invoice No</b></td><td>' . $invoice->invoice_no . '</td></tr>
                        <tr><td><b>Gold Rate (USD/oz)</b></td><td>' . number_format($goldRateUsdOz, 2)  . '</td></tr>
                        <tr><td><b>Gold Rate (AED/oz)</b></td><td>' . number_format($goldRateAedOz, 2)  . '</td></tr>
                        <tr><td><b>Gold Rate (AED/g)</b></td><td>'  . number_format($invoice->gold_rate_aed, 4) . '</td></tr>
                        <tr><td><b>Diamond Rate (' . $invoice->currency . '/Ct)</b></td><td>' . number_format($diamondRateDisplay, 2) . '</td></tr>
                    </table>
                </td>
            </tr>
        </table>';
        $pdf->writeHTML($customerHtml, true, false, false, false);

        $html = '
        <table border="1" cellpadding="3" width="100%" style="font-size:8px;">
            <thead>
                <tr style="font-weight:bold;background-color:#f5f5f5;text-align:center;">
                    <th width="3%"  rowspan="2">#</th>
                    <th width="12%" rowspan="2">Item Name</th>
                    <th width="14%" rowspan="2">Description</th>
                    <th width="6%"  rowspan="2">Gross Wt</th>
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
            $productTotal = $item->item_total;

            $html .= '
                <tr style="text-align:center;background-color:#ffffff;">
                    <td width="3%">' . ($index + 1) . '</td>
                    <td width="12%">' . ($item->item_name ?: ($item->product->name ?? '-')) . '</td>
                    <td width="14%">' . ($item->item_description ?? '-') . '</td>
                    <td width="6%">' . number_format($item->gross_weight, 3) . '</td>
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

            // FEATURE (discount, item-wise): shown as its own sub-row so the
            // 14-column table layout is unchanged.
            if ((float) ($item->discount_amount ?? 0) > 0) {
                $discLabel = ($item->discount_type ?? '') === 'percent'
                    ? 'Discount (' . rtrim(rtrim(number_format((float) $item->discount_value, 2), '0'), '.') . '%)'
                    : 'Discount';
                $html .= '<tr style="background-color:#fff8e6;font-style:italic;font-size:7px;">
                            <td></td><td colspan="11" align="right"><b>' . $discLabel . ' (already deducted from Item Total):</b></td>
                            <td colspan="2" align="right">-' . number_format((float) $item->discount_amount, 2) . '</td>
                          </tr>';
            }

            if ($hasParts) {
                $html .= '<tr style="background-color:#f9f9f9;font-style:italic;font-size:7px;">
                            <td></td><td colspan="13"><b>Parts Detail:</b></td>
                          </tr>';

                foreach ($item->parts as $part) {
                    $displayPartName = $part->item_name ?: ($part->product->name ?? 'Part');
                    $html .= '
                    <tr style="font-size:7px;background-color:#fcfcfc;text-align:center;">
                        <td width="3%"></td>
                        <td width="12%" style="text-align:left;">' . $displayPartName . '</td>
                        <td width="14%" style="text-align:left;">' . htmlspecialchars($part->part_description ?? '') . '</td>
                        <td colspan="2">' . number_format($part->qty, 3) . ' Ct</td>
                        <td colspan="2">Rate:' . number_format($part->rate, 2) . '</td>
                        <td colspan="2">St.' . number_format($part->stone_qty ?? 0, 2) . '</td>
                        <td colspan="2">SR:' . number_format($part->stone_rate ?? 0, 2) . '</td>
                        <td colspan="3" style="font-weight:bold;" align="right">' . number_format($part->total, 2) . '</td>
                    </tr>';
                }

                $html .= '
                    <tr style="background-color:#eeeeee;font-weight:bold;font-size:8px;">
                        <td colspan="12" align="right">Product Grand Total (Material + MC + Parts + VAT):</td>
                        <td colspan="2" align="right">' . number_format($productTotal, 2) . '</td>
                    </tr>';
            }
        }

        $html .= '
                <tr style="font-weight:bold;background-color:#f5f5f5;">
                    <td colspan="13" align="right">Net Invoice Amount</td>
                    <td align="right">' . number_format($invoice->net_amount, 2) . '</td>
                </tr>
            </tbody>
        </table>';

        $pdf->writeHTML($html, true, false, false, false);

        $aedAmount = $invoice->currency === 'USD' ? $invoice->net_amount_aed : $invoice->net_amount;

        $printGrandTotal = ($invoice->invoice_vat_amount ?? 0) > 0
            ? ($invoice->grand_total ?? $aedAmount)
            : $aedAmount;

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
        if ($invoice->payment_method === 'cash' && $invoice->cash_amount_paid > 0) {
            $summaryHtml .= '<tr><td>Cash Received</td><td>' . number_format($invoice->cash_amount_paid, 2) . '</td></tr>';
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
            <tr><td>Customer Bank</td><td>'   . ($invoice->transfer_to_bank ?? '-') . '</td></tr>
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
                        <tr><td>VAT on MC</td>                               <td align="right">' . number_format($totalVatAed, 2) . '</td></tr>'
                        . ($totalItemDiscount > 0 ? '<tr><td>Item Discounts</td><td align="right">-' . number_format($totalItemDiscount, 2) . '</td></tr>' : '')
                        . ($invoiceDiscount   > 0 ? '<tr><td>Invoice Discount' . (($invoice->discount_type ?? '') === 'percent' ? ' (' . rtrim(rtrim(number_format((float) $invoice->discount_value, 2), '0'), '.') . '%)' : '') . '</td><td align="right">-' . number_format($invoiceDiscount, 2) . '</td></tr>' : '')
                        . '
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
                        <tr><td>Total (AED)</td><td align="right">' . number_format($aedAmount, 2) . '</td></tr>';
        } else {
            $summaryHtml .= '<tr><td>Total (AED)</td><td align="right">' . number_format($aedAmount, 2) . '</td></tr>';
        }

        if (($invoice->invoice_vat_amount ?? 0) > 0) {
            $summaryHtml .= '
                        <tr><td>Invoice VAT (' . number_format($invoice->invoice_vat_percent, 2) . '%)</td>
                            <td align="right">' . number_format($invoice->invoice_vat_amount, 2) . '</td></tr>
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
                <b>TERMS & CONDITIONS:</b> Goods sold on credit, if not paid when due, or in case of law suit arising there from,
                the purchaser agrees to pay the seller all expense of recovery, collection, etc., including attorney fees,
                legal expense and/or recovery-agent charges. <b>GOODS ONCE SOLD CANNOT BE RETURNED OR EXCHANGED.</b>
                Any dispute arising out of or in connection with this sale shall be subject to the exclusive jurisdiction of Dubai Courts.
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

        return $pdf->Output($invoice->invoice_no . '.pdf', 'I');
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

    public function exportExcel($id)
    {
        if (!class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class)) {
            return back()->with('error', 'Excel export needs the phpoffice/phpspreadsheet package. Run: composer require phpoffice/phpspreadsheet');
        }

        $invoice = SaleInvoice::with([
            'customer',
            'items',
            'items.product.measurementUnit',
            'items.parts',
            'items.parts.product.measurementUnit',
            'bank',
            'transferBank',
        ])->findOrFail($id);

        try {
            $spreadsheet = $this->buildInvoiceSpreadsheet($invoice);
        } catch (\Throwable $e) {
            Log::error('Sale Invoice Excel Export Error', [
                'invoice_id' => $invoice->id,
                'message'    => $e->getMessage(),
                'line'       => $e->getLine(),
                'file'       => $e->getFile(),
            ]);
            return back()->with('error', 'Excel export failed: ' . $e->getMessage());
        }

        $filename = preg_replace('/[^A-Za-z0-9._-]/', '_', (string) $invoice->invoice_no) . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function buildInvoiceSpreadsheet(SaleInvoice $invoice)
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle(substr(preg_replace('/[\\\\\/\?\*\[\]:]/', '-', (string) $invoice->invoice_no), 0, 31) ?: 'Invoice');

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
        if (str_contains((string) $invoice->payment_method, 'material')) {
            $currencyPayable -= $totalDiscount;
        }

        $aedAmount  = $invoice->currency === 'USD' ? $invoice->net_amount_aed : $invoice->net_amount;
        $grandTotal = ($invoice->invoice_vat_amount ?? 0) > 0 ? ($invoice->grand_total ?? $aedAmount) : $aedAmount;

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
        $sheet->setShowGridlines(false); // Worksheet method (SheetView has no such setter)

        // ── header: logo (left) + company details (right) ─────────────────
        for ($r = 1; $r <= 4; $r++) {
            $sheet->getRowDimension($r)->setRowHeight(18);
        }
        $logoPath = public_path('assets/img/mj-logo.jpeg');
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
            "MUSFIRA JEWELRY L.L.C\nSuite #M04, Mezzanine floor, Al Buteen 2 Building, Gold Souq. Gate no.1, Deira, Dubai\nTRN No: 104902647700003",
            [$right]);

        // ── title ─────────────────────────────────────────────────────────
        $put('A6:N6', $invoice->is_taxable ? 'TAX INVOICE (SALE)' : 'SALE INVOICE', [$center, $bold]);
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
            ['Date',                                     \Carbon\Carbon::parse($invoice->invoice_date)->format('d.m.Y'), null],
            ['Invoice No',                               (string) $invoice->invoice_no,                                   null],
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

        $put("A{$row}:M{$row}", 'Net Invoice Amount', [$thin, $headFill, $bold, $right]);
        $put("N{$row}", (float) $invoice->net_amount, [$thin, $headFill, $bold, $right], $FMT_MONEY);
        $row += 2;

        // ── payment details (left) & summary (right), side by side ─────────
        $startRow = $row;

        $payRows = [['Method', ucfirst((string) $invoice->payment_method), null]];
        if ($invoice->payment_method === 'credit') {
            $payRows[] = ['Payment Term', (string) ($invoice->payment_term ?? '-'), null];
        }
        if ($invoice->payment_method === 'cash' && $invoice->cash_amount_paid > 0) {
            $payRows[] = ['Cash Received', (float) $invoice->cash_amount_paid, $FMT_MONEY];
        }
        if ($invoice->payment_method === 'cheque') {
            $payRows[] = ['Bank Name', (string) ($invoice->bank->name ?? '-'), null];
            $payRows[] = ['Cheque No', (string) ($invoice->cheque_no ?? '-'), null];
            $payRows[] = ['Cheque Date', $invoice->cheque_date ? \Carbon\Carbon::parse($invoice->cheque_date)->format('d.m.Y') : '-', null];
        }
        if ($invoice->payment_method === 'bank_transfer') {
            $payRows[] = ['From Bank', (string) ($invoice->transferBank->name ?? '-'), null];
            $payRows[] = ['Customer Bank', (string) ($invoice->transfer_to_bank ?? '-'), null];
            $payRows[] = ['Account Title', (string) ($invoice->account_title ?? '-'), null];
            $payRows[] = ['Account No', (string) ($invoice->account_no ?? '-'), null];
            $payRows[] = ['Transfer Date', $invoice->transfer_date ? \Carbon\Carbon::parse($invoice->transfer_date)->format('d.m.Y') : '-', null];
            $payRows[] = ['Transaction Ref', (string) ($invoice->transaction_id ?? '-'), null];
            $payRows[] = ['Transfer Amount', (float) ($invoice->transfer_amount ?? 0), $FMT_MONEY];
        }
        if (str_contains((string) $invoice->payment_method, 'material')) {
            $payRows[] = ['Material Given By', (string) ($invoice->material_given_by ?? '-'), null];
            $payRows[] = ['Material Received By', (string) ($invoice->material_received_by ?? '-'), null];
            $payRows[] = ['Total Pure Weight (gms)', (float) $invoice->items->sum('purity_weight'), $FMT_WT];
            $payRows[] = ['Making Charges (AED)', $totalMaking, $FMT_MONEY];
        }

        $put("A{$row}:F{$row}", 'Payment Details', [$thin, $headFill, $bold, $left]);
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
                ? ' (' . rtrim(rtrim(number_format((float) $invoice->discount_value, 2), '0'), '.') . '%)'
                : '';
            $sumRows[] = ['Invoice Discount' . $pct, -1 * $invoiceDiscount, false];
        }
        $sumRows[] = ['Currency Payable (MC + Parts + VAT)', $currencyPayable, true];
        $sumRows[] = ['Invoice Total', (float) $invoice->net_amount, true];
        if ($invoice->currency === 'USD') {
            $sumRows[] = ['Exchange Rate', (float) $invoice->exchange_rate, false, '0.0000'];
        }
        $sumRows[] = ['Total (AED)', (float) $aedAmount, false];
        if (($invoice->invoice_vat_amount ?? 0) > 0) {
            $sumRows[] = ['Invoice VAT (' . number_format((float) $invoice->invoice_vat_percent, 2) . '%)', (float) $invoice->invoice_vat_amount, false];
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
            'TERMS & CONDITIONS: Goods sold on credit, if not paid when due, or in case of law suit arising there from, ' .
            'the purchaser agrees to pay the seller all expense of recovery, collection, etc., including attorney fees, ' .
            'legal expense and/or recovery-agent charges. GOODS ONCE SOLD CANNOT BE RETURNED OR EXCHANGED. ' .
            'Any dispute arising out of or in connection with this sale shall be subject to the exclusive jurisdiction of Dubai Courts.',
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

    // =========================================================================
    // DESTROY
    // =========================================================================

    public function destroy($id)
    {
        $invoice = SaleInvoice::findOrFail($id);

        DB::beginTransaction();
        try {
            $voucher = Voucher::where('reference_type', SaleInvoice::class)
                ->where('reference_id', $invoice->id)
                ->first();
            if ($voucher) {
                AccountingEntry::where('voucher_id', $voucher->id)->delete();
                $voucher->delete();
            }
            $invoice->delete();
            DB::commit();
            return redirect()->route('sale_invoices.index')->with('success', 'Invoice deleted.');
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->with('error', 'Error: ' . $e->getMessage());
        }
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    private function generateInvoiceNo(bool $isTaxable): string
    {
        $prefix = $isTaxable ? 'SAL-TAX-' : 'SAL-';

        // FIX (stuck invoice number): this used to pick "the row with the
        // highest id" via orderByDesc('id')->first() and assume that row also
        // had the highest invoice number. Those can disagree — a row edited
        // directly in the database, imported, or restored from a backup — and
        // when they do, this silently locked onto an OLD invoice as "last"
        // and kept re-issuing an already-used number FOREVER (every single
        // attempt, not just concurrent ones), crashing on the unique
        // invoice_no constraint every time.
        //
        // Fix: ask the database for the true highest number actually used in
        // this series (MAX of the numeric suffix), instead of trusting
        // insertion order to match numeric order.
        $prefixLen = strlen($prefix);

        $maxNo = SaleInvoice::withTrashed()
            ->whereRaw(
                'invoice_no REGEXP ?',
                ['^' . preg_quote($prefix, '/') . '[0-9]+$']
            )
            ->lockForUpdate()
            ->selectRaw('MAX(CAST(SUBSTRING(invoice_no, ?) AS UNSIGNED)) as max_no', [$prefixLen + 1])
            ->value('max_no');

        $next = ((int) $maxNo) + 1;

        return $prefix . str_pad($next, 5, '0', STR_PAD_LEFT);
    }

    private function createItems(
        SaleInvoice $invoice,
        array $items,
        Request $request,
        int $startPosition = 1,
        bool $preservePrinted = false
    ): array {
        $totals = [
            'gold_material'    => 0.0,
            'diamond_material' => 0.0,
            'material'         => 0.0,
            'making'           => 0.0,
            'gold_parts'       => 0.0,
            'diamond_parts'    => 0.0,
            'diamond_val'      => 0.0,
            'stone_val'        => 0.0,
            'vat'              => 0.0,
        ];

        $position        = $startPosition;
        $goldRateAedGram = (float) ($request->gold_rate_aed    ?? 0);
        $diamondRateAed  = (float) ($request->diamond_rate_aed ?? 0);

        foreach ($items as $itemData) {
            $grossWeight = (float) ($itemData['gross_weight'] ?? 0);
            $purity      = (float) ($itemData['purity']       ?? 0);
            $makingRate  = (float) ($itemData['making_rate']  ?? 0);
            $vatPercent  = $invoice->is_taxable ? (float) ($itemData['vat_percent'] ?? 0) : 0.0;
            $matType     = $itemData['material_type'] ?? 'gold';

            $purityWeight = $grossWeight * $purity;
            $col995       = $purityWeight > 0 ? $purityWeight / 0.995 : 0;

            // FEATURE (Sale Invoice POS): items[] coming from the POS screen
            // (resources/views/sales/pos.blade.php) carry a flat, manually-set
            // selling_price instead of the rate/making-rate inputs the full
            // Sale Invoice screen uses. That price IS the item's full value,
            // verbatim — never derived from purity_weight x rate, a making
            // rate, or any margin/markup calculation (per the requirement
            // that POS never computes price from cost). Routing it through
            // $materialValue (rather than adding a parallel pricing path)
            // means createSaleAccountingEntries() below needs ZERO changes:
            // it still credits 401001/401002 Sales Revenue by material_type
            // exactly like every other Sale Invoice item, and the existing
            // invoice-level VAT mechanism (invoice_vat_percent) still applies
            // normally on top of the invoice total.
            $isPosFlatPrice = isset($itemData['selling_price'])
                && $itemData['selling_price'] !== ''
                && $itemData['selling_price'] !== null;

            if ($isPosFlatPrice) {
                $rate          = 0.0;
                $makingValue   = 0.0;
                $materialValue = round((float) $itemData['selling_price'], 2);
            } else {
                $makingValue   = $grossWeight * $makingRate;
                $rate          = $matType === 'gold' ? $goldRateAedGram : $diamondRateAed;
                $materialValue = $rate * $purityWeight;
            }

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

            // FEATURE (discount, item-wise): optional per-item discount, either
            // a flat amount or a % (items[N][discount_type] = amount|percent,
            // items[N][discount_value]) in the invoice's currency.
            //
            // The discount reduces the item's price, so the VAT charged on its
            // making (VAT base = making) shrinks by the same proportion — VAT is
            // always on the discounted value. The stored material_value /
            // making_value / parts_total stay at their ORIGINAL (undiscounted)
            // figures so the printout still reconciles to rate x weight; the
            // discount itself is stored in discount_amount and subtracted in
            // item_total.
            //
            // Material-payment invoices ("material" / "material+making cost"):
            // the customer hands over metal by weight, which a discount must
            // not change — so there the discount only applies to the currency
            // portion (making + parts + its VAT), and material value is left
            // untouched. For every other payment method the discount applies
            // to the whole item (material + making + parts).
            $materialPayment = $this->isMaterialPayment($request);
            $discountBase    = ($materialPayment ? 0.0 : $materialValue) + $makingValue + $partsTotal;
            [$discType, $discValue, $discAmount] = $this->resolveDiscount(
                $itemData['discount_type']  ?? null,
                $itemData['discount_value'] ?? 0,
                $discountBase
            );
            $discFactor = $discountBase > 0 ? ($discountBase - $discAmount) / $discountBase : 1.0;
            $factorMat  = $materialPayment ? 1.0 : $discFactor; // factor for the material component

            $taxableAmount = $makingValue * $discFactor;
            $vatAmount     = $taxableAmount * ($vatPercent / 100);
            $itemTotal     = $materialValue + $makingValue + $partsTotal - $discAmount + $vatAmount;

            $existingBarcode   = $itemData['barcode_number'] ?? null;
            $wasAlreadyPrinted = false;
            if ($preservePrinted && $existingBarcode) {
                $wasAlreadyPrinted = SaleInvoiceItem::where('barcode_number', $existingBarcode)
                    ->value('is_printed') ?? false;
            }

            $invoiceItem = $invoice->items()->create([
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
                'barcode_number'   => $existingBarcode ?: null,
                'is_printed'       => $wasAlreadyPrinted,
            ]);

            // FEATURE (discount): written with forceFill() so it works without
            // touching SaleInvoiceItem::$fillable. Only written when a discount
            // exists, so invoices without one behave exactly as before.
            if ($discAmount > 0) {
                $invoiceItem->forceFill([
                    'discount_type'   => $discType,
                    'discount_value'  => $discValue,
                    'discount_amount' => $discAmount,
                ])->save();
            }

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

            // FEATURE (discount): accounting totals carry the DISCOUNTED amounts,
            // so every revenue credit is already net of the discount ("reduce
            // revenue directly") and the debit side (receivable / cash / bank)
            // — which is built from the sum of these credits — follows.
            if ($matType === 'gold') {
                $totals['gold_material'] += $materialValue * $factorMat;
                $totals['gold_parts']    += $partsTotal    * $discFactor;
            } else {
                $totals['diamond_material'] += $materialValue * $factorMat;
                $totals['diamond_parts']    += $partsTotal    * $discFactor;
            }
            $totals['material']    += $materialValue   * $factorMat;
            $totals['making']      += $makingValue     * $discFactor;
            $totals['diamond_val'] += $itemDiamondVal  * $discFactor;
            $totals['stone_val']   += $itemStoneVal    * $discFactor;
            $totals['vat']         += $vatAmount; // already on the discounted making

            $position++;
        }

        return [$totals, $position];
    }

    // =========================================================================
    // DISCOUNT HELPERS
    // =========================================================================

    private function isMaterialPayment(Request $request): bool
    {
        return str_contains((string) $request->payment_method, 'material');
    }

    /**
     * Turns what the user typed (type = amount|percent, value) into the
     * actual discount for a given base amount. The discount can never be
     * negative or exceed the base. Returns [type|null, value, amount].
     */
    private function resolveDiscount($type, $value, float $base): array
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

        return [$type, $value, round(min($amount, $base), 2)];
    }

    /**
     * FEATURE (discount, overall invoice amount): optional invoice-level
     * discount (invoice_discount_type = amount|percent, invoice_discount_value)
     * applied on top of the already item-discounted item totals.
     *
     * Returns [net amount after the discount, accounting totals scaled to
     * match]. The scaling is proportional across the revenue components
     * (gold/diamond/making/parts/VAT), so each revenue account is credited
     * its share net of the discount and debits (built from the credits)
     * still balance. For material-payment invoices only the currency
     * portion (making + parts + VAT) is discountable — see createItems().
     *
     * The discount is stored on the invoice via forceFill() (no change to
     * SaleInvoice::$fillable needed) and reset to zero on an edit that
     * removes it.
     */
    private function applyInvoiceLevelDiscount(SaleInvoice $invoice, array $totals, Request $request): array
    {
        $netBefore       = (float) $invoice->items()->sum('item_total');
        $materialPayment = $this->isMaterialPayment($request);

        $currencyPortion = $totals['making'] + $totals['gold_parts'] + $totals['diamond_parts'] + $totals['vat'];
        $base            = $materialPayment ? $currencyPortion : $netBefore;

        [$type, $value, $amount] = $this->resolveDiscount(
            $request->invoice_discount_type,
            $request->invoice_discount_value,
            (float) $base
        );

        if ($amount > 0) {
            $g = ($base - $amount) / $base;

            foreach (['making', 'gold_parts', 'diamond_parts', 'vat', 'diamond_val', 'stone_val'] as $key) {
                $totals[$key] *= $g;
            }
            if (!$materialPayment) {
                foreach (['gold_material', 'diamond_material', 'material'] as $key) {
                    $totals[$key] *= $g;
                }
            }

            $invoice->forceFill([
                'discount_type'   => $type,
                'discount_value'  => $value,
                'discount_amount' => $amount,
            ])->save();
        } elseif ((float) ($invoice->discount_amount ?? 0) > 0) {
            // edit that removed a previously saved invoice-level discount
            $invoice->forceFill([
                'discount_type'   => null,
                'discount_value'  => 0,
                'discount_amount' => 0,
            ])->save();
        }

        return [round($netBefore - $amount, 2), $totals];
    }

    private function storeAttachments(Request $request, SaleInvoice $invoice): void
    {
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('sale_invoices', 'public');
                $invoice->attachments()->create(['file_path' => $path]);
            }
        }
    }

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

        // FIX (material ): server-side hardening — even if the JS-hidden
        // collection fields were somehow submitted (e.g. tampered request),
        // 'material ' NEVER allows a cash/bank collection. This is what
        // makes making_amount_collected correctly persist as 0 for this
        // method, keeping it consistent with the making_amount_collected fix.
        if ($request->payment_method === 'material') {
            $request->merge([
                'making_amount_paid'     => 0,
                'making_payment_account' => null,
            ]);
        }
    }

    private function validateInvoice(Request $request): void
    {
        $request->validate([
            'is_taxable'               => 'required|boolean',
            'customer_id'              => 'required|exists:chart_of_accounts,id',
            'consignment_id'           => 'nullable|exists:consignments,id',
            'invoice_date'             => 'required|date',
            'currency'                 => 'required|in:AED,USD',
            'exchange_rate'            => 'nullable|required_if:currency,USD|numeric|min:0',
            'net_amount'               => 'required|numeric|min:0',
            'invoice_vat_percent'      => 'nullable|numeric|min:0|max:100',
            'payment_method'           => 'required|in:credit,cash,cheque,bank_transfer,material+making cost,material',
            'payment_term'             => 'nullable|string',
            'gold_rate_usd'            => 'nullable|numeric|min:0',
            'gold_rate_aed_ounce'      => 'nullable|numeric|min:0',
            'gold_rate_aed'            => 'nullable|numeric|min:0',
            'diamond_rate_usd'         => 'nullable|numeric|min:0',
            'diamond_rate_aed'         => 'nullable|numeric|min:0',
            'purchase_gold_rate_aed'   => 'nullable|numeric|min:0',
            'purchase_making_rate_aed' => 'nullable|numeric|min:0',
            'bank_name'                => 'nullable|required_if:payment_method,cheque|exists:chart_of_accounts,id',
            'cheque_no'                => 'nullable|required_if:payment_method,cheque|string',
            'cheque_date'              => 'nullable|required_if:payment_method,cheque|date',
            'cheque_amount'            => 'nullable|required_if:payment_method,cheque|numeric|min:0',
            'transfer_from_bank'       => 'nullable|required_if:payment_method,bank_transfer|exists:chart_of_accounts,id',
            'transfer_to_bank'         => 'nullable|string',
            'account_title'            => 'nullable|string',
            'account_no'               => 'nullable|string',
            'transaction_id'           => 'nullable|string',
            'transfer_date'            => 'nullable|required_if:payment_method,bank_transfer|date',
            'transfer_amount'          => 'nullable|required_if:payment_method,bank_transfer|numeric|min:0',
            'items'                    => 'required|array|min:1',
            'items.*.item_name'        => 'nullable|string|required_without:items.*.product_id',
            'items.*.product_id'       => 'nullable|exists:products,id|required_without:items.*.item_name',
            'items.*.gross_weight'     => 'required|numeric|min:0',
            'items.*.purity'           => 'required|numeric|min:0|max:1',
            'items.*.making_rate'      => 'required|numeric|min:0',
            'items.*.material_type'    => 'required|in:gold,diamond',
            'items.*.vat_percent'      => 'required|numeric|min:0',
            // FEATURE (discount): optional, item-wise and whole-invoice. Blank
            // fields arrive as null (ConvertEmptyStringsToNull) and mean "no discount".
            'items.*.discount_type'    => 'nullable|in:amount,percent',
            'items.*.discount_value'   => 'nullable|numeric|min:0',
            'invoice_discount_type'    => 'nullable|in:amount,percent',
            'invoice_discount_value'   => 'nullable|numeric|min:0',
            // FEATURE (Sale Invoice POS): optional flat retail price coming
            // from the POS screen — see createItems() for how it overrides
            // the normal rate x purity_weight calculation.
            'items.*.selling_price'    => 'nullable|numeric|min:0',
            'material_given_by'        => ['nullable', 'string', 'required_if:payment_method,material+making cost', 'required_if:payment_method,material'],
            'material_received_by'     => ['nullable', 'string', 'required_if:payment_method,material+making cost', 'required_if:payment_method,material'],
            'cash_amount_paid'         => 'nullable|numeric|min:0',
            'making_amount_paid'       => 'nullable|numeric|min:0',
            'making_payment_account'   => [
                'nullable',
                'string',
                function ($attribute, $value, $fail) use ($request) {
                    if ($request->payment_method !== 'material+making cost') {
                        return;
                    }

                    $paidRaw = $request->making_amount_paid;
                    $paid    = ($paidRaw !== null && $paidRaw !== '') ? (float) $paidRaw : 0.0;

                    if ($paid <= 0) {
                        return;
                    }

                    if (empty($value)) {
                        $fail('Please select a Cash/Bank account — making charges collected now is greater than 0.');
                        return;
                    }

                    if ($value !== 'cash' && !str_starts_with($value, 'bank_')) {
                        $fail('Invalid Cash/Bank account selected.');
                        return;
                    }

                    if (str_starts_with($value, 'bank_')) {
                        $accountId = (int) str_replace('bank_', '', $value);
                        $exists = ChartOfAccounts::whereIn('account_type', ['bank', 'cash'])
                            ->where('id', $accountId)
                            ->exists();

                        if (!$exists) {
                            $fail('Selected Cash/Bank account is invalid.');
                        }
                    }
                },
            ],
        ]);
    }

    protected function createSaleAccountingEntries(SaleInvoice $invoice, array $totals, Request $request): Voucher
    {
        $acct = function (string $code) use ($invoice): int {
            $account = ChartOfAccounts::where('account_code', $code)->first();
            if (!$account) {
                throw new \Exception(
                    "Account code [{$code}] not found in Chart of Accounts (Invoice #{$invoice->invoice_no}). " .
                    "Run the database seeder or create this account manually."
                );
            }
            return $account->id;
        };

        $isMaterial = str_contains($invoice->payment_method, 'material');

        // FIX (Material Only): distinct remarks label so vouchers clearly show
        // which material-based flow generated them.
        $remarksSuffix = match ($invoice->payment_method) {
            'material+making cost'  => ' [Metal Receipt + Currency Collection]',
            'material'              => ' [Metal Receipt Only — No Cash Collected]',
            default                 => '',
        };

        $voucher = Voucher::create([
            'voucher_no'     => Voucher::generateVoucherNo('sale'),
            'voucher_type'   => 'sale',
            'voucher_date'   => $invoice->invoice_date,
            'reference_type' => SaleInvoice::class,
            'reference_id'   => $invoice->id,
            'ac_dr_sid'      => null,
            'ac_cr_sid'      => null,
            'amount'         => null,
            'remarks'        => 'Sale Invoice #' . $invoice->invoice_no . $remarksSuffix,
            'created_by'     => auth()->id(),
        ]);

        $entries = [];

        $goldRev    = round($totals['gold_material']    + $totals['gold_parts'],    2);
        $diamondRev = round($totals['diamond_material'] + $totals['diamond_parts'], 2);

        if ($goldRev > 0) {
            $entries[] = [
                'voucher_id' => $voucher->id,
                'account_id' => $acct('401001'),
                'debit'      => 0,
                'credit'     => $goldRev,
                'narration'  => 'Gold material + parts revenue — Inv# ' . $invoice->invoice_no,
            ];
        }

        if ($diamondRev > 0) {
            $entries[] = [
                'voucher_id' => $voucher->id,
                'account_id' => $acct('401002'),
                'debit'      => 0,
                'credit'     => $diamondRev,
                'narration'  => 'Diamond material + parts revenue — Inv# ' . $invoice->invoice_no,
            ];
        }

        if ($totals['making'] > 0) {
            $entries[] = [
                'voucher_id' => $voucher->id,
                'account_id' => $acct('402001'),
                'debit'      => 0,
                'credit'     => round($totals['making'], 2),
                'narration'  => 'Making charges income — Inv# ' . $invoice->invoice_no,
            ];
        }

        if ($totals['vat'] > 0) {
            $entries[] = [
                'voucher_id' => $voucher->id,
                'account_id' => $acct('208001'),
                'debit'      => 0,
                'credit'     => round($totals['vat'], 2),
                'narration'  => 'Output VAT on making charges — Inv# ' . $invoice->invoice_no,
            ];
        }

        if (($invoice->invoice_vat_amount ?? 0) > 0) {
            $entries[] = [
                'voucher_id' => $voucher->id,
                'account_id' => $acct('208001'),
                'debit'      => 0,
                'credit'     => round($invoice->invoice_vat_amount, 2),
                'narration'  => 'Output VAT ' . number_format($invoice->invoice_vat_percent, 2)
                                . '% on invoice total — Inv# ' . $invoice->invoice_no,
            ];
        }

        $totalCredit = round(collect($entries)->sum('credit'), 2);

        if ($totalCredit <= 0) {
            throw new \Exception(
                "Invoice #{$invoice->invoice_no} has zero accounting value — no entries created."
            );
        }

        switch ($invoice->payment_method) {

            case 'credit':
                $entries[] = [
                    'voucher_id' => $voucher->id,
                    'account_id' => $invoice->customer_id,
                    'debit'      => $totalCredit,
                    'credit'     => 0,
                    'narration'  => 'Full invoice receivable from customer (credit sale) — Inv# ' . $invoice->invoice_no,
                ];
                break;

            case 'cash':
                $collected = $request->cash_amount_paid !== null && $request->cash_amount_paid !== ''
                    ? round((float) $request->cash_amount_paid, 2)
                    : $totalCredit;

                $collected = min($collected, $totalCredit);

                if ($collected > 0) {
                    $entries[] = [
                        'voucher_id' => $voucher->id,
                        'account_id' => $acct('101001'),
                        'debit'      => $collected,
                        'credit'     => 0,
                        'narration'  => 'Cash received from customer — Inv# ' . $invoice->invoice_no,
                    ];
                }

                $remaining = round($totalCredit - $collected, 2);
                if ($remaining > 0) {
                    $entries[] = [
                        'voucher_id' => $voucher->id,
                        'account_id' => $invoice->customer_id,
                        'debit'      => $remaining,
                        'credit'     => 0,
                        'narration'  => 'Balance receivable from customer (partial cash) — Inv# ' . $invoice->invoice_no,
                    ];
                }
                break;

            case 'cheque':
                if (!$invoice->bank_name) {
                    throw new \Exception(
                        'Bank account required for cheque payment (Inv# ' . $invoice->invoice_no . ').'
                    );
                }

                $collected = $invoice->cheque_amount > 0
                    ? round((float) $invoice->cheque_amount, 2)
                    : $totalCredit;

                $collected = min($collected, $totalCredit);

                if ($collected > 0) {
                    $entries[] = [
                        'voucher_id' => $voucher->id,
                        'account_id' => $invoice->bank_name,
                        'debit'      => $collected,
                        'credit'     => 0,
                        'narration'  => 'Cheque #' . $invoice->cheque_no . ' received from customer — Inv# ' . $invoice->invoice_no,
                    ];
                }

                $remaining = round($totalCredit - $collected, 2);
                if ($remaining > 0) {
                    $entries[] = [
                        'voucher_id' => $voucher->id,
                        'account_id' => $invoice->customer_id,
                        'debit'      => $remaining,
                        'credit'     => 0,
                        'narration'  => 'Balance receivable from customer (partial cheque) — Inv# ' . $invoice->invoice_no,
                    ];
                }
                break;

            case 'bank_transfer':
                if (!$invoice->transfer_from_bank) {
                    throw new \Exception(
                        'Transfer-from bank required for bank transfer (Inv# ' . $invoice->invoice_no . ').'
                    );
                }

                $collected = $invoice->transfer_amount > 0
                    ? round((float) $invoice->transfer_amount, 2)
                    : $totalCredit;

                $collected = min($collected, $totalCredit);

                if ($collected > 0) {
                    $entries[] = [
                        'voucher_id' => $voucher->id,
                        'account_id' => $invoice->transfer_from_bank,
                        'debit'      => $collected,
                        'credit'     => 0,
                        'narration'  => 'Bank transfer Ref# ' . $invoice->transaction_id
                                        . ' received from customer — Inv# ' . $invoice->invoice_no,
                    ];
                }

                $remaining = round($totalCredit - $collected, 2);
                if ($remaining > 0) {
                    $entries[] = [
                        'voucher_id' => $voucher->id,
                        'account_id' => $invoice->customer_id,
                        'debit'      => $remaining,
                        'credit'     => 0,
                        'narration'  => 'Balance receivable from customer (partial transfer) — Inv# ' . $invoice->invoice_no,
                    ];
                }
                break;

            case 'material+making cost':
                $materialDebit = round($totals['gold_material'] + $totals['diamond_material'], 2);
                $currencyTotal = round($totalCredit - $materialDebit, 2);

                $makingPaidRaw = $request->making_amount_paid;
                $makingPaid    = ($makingPaidRaw !== null && $makingPaidRaw !== '')
                    ? round((float) $makingPaidRaw, 2)
                    : 0.0;

                $makingPaid = min($makingPaid, max(0, $currencyTotal));

                if ($materialDebit > 0) {
                    $entries[] = [
                        'voucher_id' => $voucher->id,
                        'account_id' => $acct('104001'),
                        'debit'      => $materialDebit,
                        'credit'     => 0,
                        'narration'  => 'Gold received from customer as material payment'
                                        . ' (' . ($invoice->material_received_by ?? 'customer') . ')'
                                        . ' — Inv# ' . $invoice->invoice_no,
                    ];
                }

                $currencyRemaining = round($currencyTotal - $makingPaid, 2);
                if ($currencyRemaining > 0) {
                    $entries[] = [
                        'voucher_id' => $voucher->id,
                        'account_id' => $invoice->customer_id,
                        'debit'      => $currencyRemaining,
                        'credit'     => 0,
                        'narration'  => 'MC + parts + VAT outstanding from customer — Inv# ' . $invoice->invoice_no,
                    ];
                }

                if ($makingPaid > 0) {
                    $paymentAccountId = null;
                    $paymentLabel     = '';

                    if ($request->making_payment_account === 'cash') {
                        $paymentAccountId = $acct('101001');
                        $paymentLabel     = 'Cash in Hand';
                    } elseif (str_starts_with((string) $request->making_payment_account, 'bank_')) {
                        $paymentAccountId = (int) str_replace('bank_', '', $request->making_payment_account);
                        $paymentLabel     = ChartOfAccounts::find($paymentAccountId)?->name ?? 'Bank/Cash';
                    }

                    if (!$paymentAccountId) {
                        throw new \Exception(
                            'Cash/Bank account for making charges collected could not be resolved — Inv# ' . $invoice->invoice_no
                        );
                    }

                    $entries[] = [
                        'voucher_id' => $voucher->id,
                        'account_id' => $paymentAccountId,
                        'debit'      => $makingPaid,
                        'credit'     => 0,
                        'narration'  => $paymentLabel . ' received for making charges from customer — Inv# ' . $invoice->invoice_no,
                    ];
                }
                break;
            case 'material':
                // Customer gives ONLY raw material — no cash/bank collection
                // is ever allowed under this method (enforced server-side in
                // clearIrrelevantPaymentFields(), which forces
                // making_amount_paid = 0 regardless of what's submitted).
                //   DR Gold/Diamond Inventory — material value received
                //   DR Customer AR             — full remainder (MC + parts + VAT)
                // If material value exactly covers the invoice total, the
                // remainder is 0 and nothing is owed — the invoice is fully
                // settled by metal alone.
                $materialDebitOnly = round($totals['gold_material'] + $totals['diamond_material'], 2);

                if ($materialDebitOnly > 0) {
                    $entries[] = [
                        'voucher_id' => $voucher->id,
                        'account_id' => $acct('104001'),
                        'debit'      => $materialDebitOnly,
                        'credit'     => 0,
                        'narration'  => 'Gold received from customer as full material payment'
                                        . ' (' . ($invoice->material_received_by ?? 'customer') . ')'
                                        . ' — Inv# ' . $invoice->invoice_no,
                    ];
                }

                $remainingMaterialOnly = round($totalCredit - $materialDebitOnly, 2);
                if ($remainingMaterialOnly > 0) {
                    $entries[] = [
                        'voucher_id' => $voucher->id,
                        'account_id' => $invoice->customer_id,
                        'debit'      => $remainingMaterialOnly,
                        'credit'     => 0,
                        'narration'  => 'Balance receivable from customer (material value below invoice total) — Inv# ' . $invoice->invoice_no,
                    ];
                }
                break;

            default:
                throw new \Exception('Unrecognised payment method: "' . $invoice->payment_method . '"');
        }

        foreach ($entries as $entry) {
            AccountingEntry::create($entry);
        }

        $sumDebits  = round(collect($entries)->sum('debit'),  2);
        $sumCredits = round(collect($entries)->sum('credit'), 2);

        if ($sumDebits !== $sumCredits) {
            throw new \Exception(
                "Accounting imbalance on Sale Invoice #{$invoice->invoice_no}: " .
                "Debits {$sumDebits} ≠ Credits {$sumCredits}."
            );
        }

        Log::info('Sale accounting entries created', [
            'invoice_no'             => $invoice->invoice_no,
            'voucher_no'             => $voucher->voucher_no,
            'payment_method'         => $invoice->payment_method,
            'cr_401001_gold'         => $goldRev,
            'cr_401002_diamond'      => $diamondRev,
            'cr_402001_making'       => round($totals['making'], 2),
            'cr_208001_vat_per_item' => round($totals['vat'], 2),
            'cr_208001_vat_invoice'  => round($invoice->invoice_vat_amount ?? 0, 2),
            'total_credit'           => $sumCredits,
            'total_debit'            => $sumDebits,
        ]);

        return $voucher;
    }

    // =========================================================================
    // PRINT SIMPLE — Walk-in / Retail receipt (unchanged)
    // =========================================================================

    public function printSimple($id)
    {
        $invoice = SaleInvoice::with([
            'customer',
            'items',
            'bank',
            'transferBank',
        ])->findOrFail($id);

        $pdf = new myPDF();
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetCreator('Musfira Jewelry');
        $pdf->SetTitle($invoice->invoice_no . ' — Receipt');
        $pdf->SetMargins(10, 10, 10);
        $pdf->setCellPadding(1.2);
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

        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 6, 'SALE RECEIPT', 0, 1, 'C');
        $pdf->Ln(2);
        $pdf->SetFont('helvetica', '', 9);

        $aedAmount = $invoice->currency === 'USD' ? $invoice->net_amount_aed : $invoice->net_amount;

        $receiptGrandTotal = ($invoice->invoice_vat_amount ?? 0) > 0
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
                    <b>Ref:</b> ' . ($invoice->customer->remarks ?? '-') . '<br>
                </td>
                <td width="50%">
                    <table border="1" cellpadding="3" width="100%">
                        <tr><td width="45%"><b>Date</b></td><td width="55%">' . Carbon::parse($invoice->invoice_date)->format('d.m.Y') . '</td></tr>
                        <tr><td><b>Invoice No</b></td><td>' . $invoice->invoice_no . '</td></tr>
                        <tr><td><b>Payment</b></td><td>' . ucwords(str_replace(['+', '_'], [' + ', ' '], $invoice->payment_method)) . '</td></tr>
                        <tr><td><b>Currency</b></td><td>' . $invoice->currency . '</td></tr>
                    </table>
                </td>
            </tr>
        </table>';
        $pdf->writeHTML($customerHtml, true, false, false, false);

        $html = '
        <table border="1" cellpadding="3" width="100%" style="font-size:8px;">
            <thead>
                <tr style="font-weight:bold;background-color:#f5f5f5;text-align:center;">
                    <th width="5%">#</th>
                    <th width="30%" style="text-align:left;">Item Name</th>
                    <th width="25%">Description</th>
                    <th width="12%">Gross Wt (g)</th>
                    <th width="10%">Material</th>
                    <th width="18%">Amount (AED)</th>
                </tr>
            </thead>
            <tbody>';

        $totalGrossWeight = 0;

        foreach ($invoice->items as $i => $item) {
            $html .= '
                <tr style="text-align:center;background-color:#ffffff;">
                    <td width="5%">' . ($i + 1) . '</td>
                    <td width="30%" style="text-align:left;">' . htmlspecialchars($item->item_name ?: ($item->product->name ?? '-')) . '</td>
                    <td width="25%" style="text-align:left;">' . htmlspecialchars($item->item_description ?? '-') . '</td>
                    <td width="12%">' . number_format($item->gross_weight, 3) . '</td>
                    <td width="10%">' . ucfirst($item->material_type) . '</td>
                    <td width="18%" style="font-weight:bold;">' . number_format($item->item_total, 2) . '</td>
                </tr>';
            $totalGrossWeight += $item->gross_weight;
        }

        $html .= '
                <tr style="font-weight:bold;background-color:#f5f5f5;text-align:center;">
                    <td colspan="3" style="text-align:right;">Net Invoice Amount</td>
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
                        <tr style="background-color:#f5f5f5;"><td><b>Payment Details</b></td><td><b>Value</b></td></tr>
                        <tr><td>Method</td><td>' . ucfirst($invoice->payment_method) . '</td></tr>';

        if ($invoice->payment_method === 'credit') {
            $summaryHtml .= '<tr><td>Payment Term</td><td>' . ($invoice->payment_term ?? '-') . '</td></tr>';
        }
        if ($invoice->payment_method === 'cash' && $invoice->cash_amount_paid > 0) {
            $summaryHtml .= '<tr><td>Cash Received</td><td>' . number_format($invoice->cash_amount_paid, 2) . '</td></tr>';
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
            <tr><td>Customer Bank</td><td>'   . ($invoice->transfer_to_bank ?? '-') . '</td></tr>
            <tr><td>Account Title</td><td>'   . ($invoice->account_title ?? '-') . '</td></tr>
            <tr><td>Account No</td><td>'      . ($invoice->account_no ?? '-') . '</td></tr>
            <tr><td>Transfer Date</td><td>'   . ($invoice->transfer_date ? Carbon::parse($invoice->transfer_date)->format('d.m.Y') : '-') . '</td></tr>
            <tr><td>Transaction Ref</td><td>' . ($invoice->transaction_id ?? '-') . '</td></tr>
            <tr><td>Transfer Amount</td><td>' . number_format($invoice->transfer_amount ?? 0, 2) . '</td></tr>';
        }
        if (str_contains($invoice->payment_method, 'material')) {
            $totalPureWeight = $invoice->items->sum('purity_weight');
            $totalMakingAed  = $invoice->items->sum('making_value');
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
                        <tr style="background-color:#f5f5f5;"><td colspan="2" align="center"><b>Summary (' . $invoice->currency . ')</b></td></tr>'
                        . ((float) ($invoice->discount_amount ?? 0) > 0
                            ? '<tr><td width="60%">Invoice Discount' . (($invoice->discount_type ?? '') === 'percent' ? ' (' . rtrim(rtrim(number_format((float) $invoice->discount_value, 2), '0'), '.') . '%)' : '') . '</td><td width="40%" align="right">-' . number_format((float) $invoice->discount_amount, 2) . '</td></tr>'
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

        if (($invoice->invoice_vat_amount ?? 0) > 0) {
            $summaryHtml .= '
                        <tr><td>VAT (' . number_format($invoice->invoice_vat_percent, 2) . '%)</td>
                            <td align="right">' . number_format($invoice->invoice_vat_amount, 2) . '</td></tr>
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
                <b>TERMS & CONDITIONS:</b> Goods sold on credit, if not paid when due, or in case of law suit arising there from,
                the purchaser agrees to pay the seller all expense of recovery, collection, etc., including attorney fees,
                legal expense and/or recovery-agent charges. <b>GOODS ONCE SOLD CANNOT BE RETURNED OR EXCHANGED.</b>
                Any dispute arising out of or in connection with this sale shall be subject to the exclusive jurisdiction of Dubai Courts.
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

        return $pdf->Output($invoice->invoice_no . '_receipt.pdf', 'I');
    }


    // =========================================================================
    // SEARCH BY NAME — Ajax typeahead for Item Name field
    // =========================================================================

    public function searchByName(Request $request)
    {
        $query = trim($request->get('q', ''));

        if (mb_strlen($query) < 2) {
            return response()->json(['success' => true, 'results' => []]);
        }

        $results = collect();

        SaleInvoiceItem::with(['parts', 'saleInvoice.customer'])
            ->where('item_name', 'like', "%{$query}%")
            ->latest()
            ->limit(10)
            ->get()
            ->each(fn($item) => $results->push($this->formatNameSearchResult($item, 'sale')));

        \App\Models\ConsignmentItem::with(['parts', 'consignment.partner'])
            ->where('item_name', 'like', "%{$query}%")
            ->where('item_status', 'in_stock')
            ->limit(10)
            ->get()
            ->each(fn($item) => $results->push(
                $this->formatNameSearchResult($item, 'consignment', $item->consignment->consignment_no ?? null)
            ));

        \App\Models\PurchaseInvoiceItem::with(['parts', 'purchaseInvoice.vendor'])
            ->where('item_name', 'like', "%{$query}%")
            ->latest()
            ->limit(10)
            ->get()
            ->each(fn($item) => $results->push($this->formatNameSearchResult($item, 'purchase')));

        $results = $results
            ->unique(fn($r) => $r['barcode_number'] ?: $r['item_name'] . '|' . $r['source'])
            ->take(15)
            ->values();

        return response()->json(['success' => true, 'results' => $results]);
    }

    private function formatNameSearchResult($item, string $source, ?string $consignmentNo = null): array
    {
        $invoiceNo   = null;
        $invoiceDate = null;
        $partyName   = null;

        switch ($source) {
            case 'sale':
                $inv         = $item->saleInvoice;
                $invoiceNo   = $inv->invoice_no ?? null;
                $invoiceDate = $inv && $inv->invoice_date
                    ? \Carbon\Carbon::parse($inv->invoice_date)->format('d-M-Y')
                    : null;
                $partyName   = $inv->customer->name ?? null;
                break;

            case 'purchase':
                $inv         = $item->purchaseInvoice;
                $invoiceNo   = $inv->invoice_no ?? null;
                $invoiceDate = $inv && $inv->invoice_date
                    ? \Carbon\Carbon::parse($inv->invoice_date)->format('d-M-Y')
                    : null;
                $partyName   = $inv->vendor->name ?? null;
                break;

            case 'consignment':
                $csg         = $item->consignment;
                $invoiceNo   = $csg->consignment_no ?? $consignmentNo;
                $invoiceDate = $csg && $csg->start_date
                    ? \Carbon\Carbon::parse($csg->start_date)->format('d-M-Y')
                    : null;
                $partyName   = $csg->partner->name ?? null;
                break;
        }

        return [
            'source'           => $source,
            'consignment_no'   => $consignmentNo,
            'barcode_number'   => $item->barcode_number,
            'item_name'        => $item->item_name,
            'item_description' => $item->item_description,
            'purity'           => $item->purity,
            'gross_weight'     => $item->gross_weight,
            'making_rate'      => $item->making_rate,
            'material_type'    => $item->material_type,
            'material_value'   => (float) ($item->material_value ?? 0),
            'vat_percent'      => $item->vat_percent,
            'invoice_no'       => $invoiceNo,
            'invoice_date'     => $invoiceDate,
            'party_name'       => $partyName,
            'product_id'       => $item->product_id ?? null,
            'parts'            => $item->parts->map(fn($p) => [
                'item_name'        => $p->item_name,
                'part_description' => $p->part_description,
                'qty'              => $p->qty,
                'rate'             => $p->rate,
                'stone_qty'        => $p->stone_qty,
                'stone_rate'       => $p->stone_rate,
                'total'            => $p->total,
            ])->values()->toArray(),
        ];
    }

    // =========================================================================
    // DOWNLOAD TEMPLATE (Excel/CSV import)
    // =========================================================================

    public function downloadTemplate()
    {
        $filename = 'sale_import_template.csv';

        $rows = [
            [
                'Item Name', 'Description', 'Purity', 'Base Gross Wt',
                'Making Rate', 'Material', 'VAT %',
                'Part Name', 'Part Desc', 'Part Qty', 'Part Rate',
                'Stone Qty', 'Stone Rate',
            ],
            ['18K Gold Bracelet', 'Handmade Chain Design', '0.75', '12.50', '25.00', 'gold', '5', '', '', '', '', '', ''],
            ['', '', '', '', '', '', '', 'Small Diamonds', 'VVS1 Round', '0.25', '1500', '10', '50'],
            ['22K Wedding Band', 'Plain Polished', '0.92', '8.75', '15.00', 'gold', '5', '', '', '', '', '', ''],
            ['Diamond Engagement Ring', 'Solitaire Setting', '0.75', '4.20', '150.00', 'gold', '5', '', '', '', '', '', ''],
            ['', '', '', '', '', '', '', 'Main Diamond', '1.0ct GIA', '1.00', '8500', '0', '0'],
            ['', '', '', '', '', '', '', 'Side Stones', 'Micro Pave', '0.50', '1200', '24', '10'],
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
}
