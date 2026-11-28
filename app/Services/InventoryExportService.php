<?php

namespace App\Services;

use App\Models\OfflineSyncBatch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * LIVE SIDE of the inventory pull feature — the "permanent", client-usable
 * replacement for the manual phpMyAdmin export + refresh-inventory.bat
 * process (see that script's own header comment for the history of why
 * this exists at all).
 *
 * purchase_invoices/purchase_invoice_items and their attachments/parts/
 * returns are deliberately NOT part of OfflineSyncExportService — purchases
 * can't be created offline at all (BlockOfflineRestrictedModules), so they
 * were only ever meant to be imported once as read-only reference data.
 * But as new stock gets purchased on the live site, an offline install's
 * copy of that reference data goes stale with no way to refresh it short of
 * a developer running raw SQL — this closes that gap with a plain button.
 *
 * Deliberately a FULL snapshot every time (not incremental like the sales
 * sync) — there is no "synced_at" tracking column on these tables, and
 * doesn't need one: InventoryImportService fully replaces its 7 tables on
 * the offline side on every import, so re-sending everything each time is
 * simply correct, not wasteful in any way that matters at this data's
 * realistic size (hundreds to low thousands of rows for a jewelry
 * business, not millions).
 *
 * AVAILABILITY FILTER (purchase_invoice_items / purchase_invoice_item_parts
 * only — every other table below is still exported in full):
 *
 * The offline side is used for POS scanning, so it should only ever see
 * physical pieces that could actually still be scanned and sold — not
 * every purchase ever made. Per the client's explicit rule, a purchased
 * item is "available" (exported) unless it's currently sold, with two
 * carve-outs:
 *   1. excluded once sold ...................... currently sold, not returned
 *   2. ... but included again if returned ....... sold, then sale-returned
 *   3. excluded while out on consignment ........ sent out to another
 *                                                  business (outbound) and
 *                                                  not yet back in stock
 *
 * This reuses the exact same two checks the rest of the app already uses
 * for "is this barcode in stock right now?" rather than inventing a new
 * definition:
 *   - SaleInvoiceItem::currentlySoldFor() — same method POS scan
 *     (SaleInvoiceController::posScan()) already calls. "Latest sale for
 *     this barcode, unless that sale was itself sale-returned" — computed
 *     here in bulk (one query, not per-barcode) but to the same result.
 *   - The outbound-consignment exclusion InventoryReportController's own
 *     buildStockInHand() uses: a ConsignmentItem still 'in_stock' whose
 *     parent Consignment.direction is 'outbound'.
 *
 * A purchase item with no barcode_number at all can't be matched against
 * either check, so it's kept (safer default — never hide real inventory
 * just because we can't prove it's gone).
 *
 * purchase_invoices / purchase_invoice_attachments / purchase_returns /
 * purchase_return_items / purchase_return_item_parts are NOT filtered —
 * they're invoice headers, file attachments, and vendor-return history,
 * not "is this physical piece available to sell" records, so they stay a
 * full export exactly as before.
 */
class InventoryExportService
{
    /** Parent-before-child — see each migration's foreign keys. */
    public const TABLES = [
        'purchase_invoices',
        'purchase_invoice_items',
        'purchase_invoice_attachments',
        'purchase_invoice_item_parts',
        'purchase_returns',
        'purchase_return_items',
        'purchase_return_item_parts',
    ];

    /**
     * @return array{batchId: string, path: string, fileName: string, counts: array<string,int>}
     */
    public function build(): array
    {
        if (config('app.offline_mode')) {
            throw new \RuntimeException('Export Inventory only runs on the live site, not on an offline install.');
        }

        $batchId = (string) Str::uuid();
        $now     = now();

        $bundle = [
            'batch_id'    => $batchId,
            'exported_at' => $now->toIso8601String(),
            'tables'      => [],
        ];
        $counts = [];

        // Surviving purchase_invoice_items IDs — computed once, used both to
        // filter that table itself and to filter purchase_invoice_item_parts
        // down to only the parts that belong to an item we're keeping (a
        // part whose parent item got dropped would otherwise be an orphan
        // foreign key on the offline side's fresh re-import).
        $availableItemIds = null;

        foreach (self::TABLES as $table) {
            $query = DB::table($table);
            if (Schema::hasColumn($table, 'deleted_at')) {
                $query->whereNull('deleted_at');
            }

            if ($table === 'purchase_invoice_items') {
                $available = $this->availableBarcodes();
                $query->where(function ($q) use ($available) {
                    $q->whereNull('barcode_number')
                        ->orWhereIn('barcode_number', $available);
                });

                $rows = $query->get();
                $availableItemIds = $rows->pluck('id')->all();
            } elseif ($table === 'purchase_invoice_item_parts') {
                $query->whereIn('purchase_invoice_item_id', $availableItemIds ?? []);
                $rows = $query->get();
            } else {
                $rows = $query->get();
            }

            $bundle['tables'][$table] = $rows->map(fn ($row) => (array) $row)->all();
            $counts[$table] = $rows->count();
        }

        $fileName = 'mj-inventory-' . $now->format('Y-m-d_His') . '-' . substr($batchId, 0, 8) . '.json';
        $dir      = storage_path('app/sync_exports');
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $path = $dir . DIRECTORY_SEPARATOR . $fileName;
        file_put_contents($path, json_encode($bundle, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        OfflineSyncBatch::create([
            'batch_id'     => $batchId,
            'direction'    => 'inv_export',
            'table_counts' => $counts,
            'file_name'    => $fileName,
            'created_by'   => auth()->id(),
        ]);

        return [
            'batchId'  => $batchId,
            'path'     => $path,
            'fileName' => $fileName,
            'counts'   => $counts,
        ];
    }

    /**
     * Every distinct barcode_number currently in purchase_invoice_items
     * that is NOT currently sold and NOT currently out on outbound
     * consignment — i.e. "available" per the client's rule. See this
     * class's own docblock for the exact rule and why each check below
     * reuses existing app logic instead of a new definition.
     *
     * @return array<int, string>
     */
    private function availableBarcodes(): array
    {
        $allBarcodes = DB::table('purchase_invoice_items')
            ->whereNotNull('barcode_number')
            ->distinct()
            ->pluck('barcode_number')
            ->all();

        if (empty($allBarcodes)) {
            return [];
        }

        $sold      = $this->currentlySoldBarcodes();
        $outbound  = $this->outboundConsignmentBarcodes();
        $unavailable = $sold + $outbound; // keys only matter — array union is fine

        return array_values(array_diff($allBarcodes, array_keys($unavailable)));
    }

    /**
     * Barcodes whose LATEST sale (by id — correlates with recency the same
     * way Eloquent's latest() does) has NOT itself been sale-returned.
     * Bulk-query equivalent of SaleInvoiceItem::currentlySoldFor($barcode)
     * !== null, run once for every barcode instead of once per barcode.
     * Also matches that method's own deleted-invoice handling: a sale whose
     * parent SaleInvoice has been (soft-)deleted doesn't count as a sale at
     * all, so its barcode is available again — see SaleInvoiceItem's own
     * docblock for why that matters (the POS-scan-after-delete bug fix).
     *
     * @return array<string, true> keyed by barcode, for fast isset() checks
     */
    private function currentlySoldBarcodes(): array
    {
        $latestSaleItemIdByBarcode = DB::table('sale_invoice_items as sii')
            ->join('sale_invoices as si', 'si.id', '=', 'sii.sale_invoice_id')
            ->whereNull('si.deleted_at')
            ->whereNotNull('sii.barcode_number')
            ->selectRaw('sii.barcode_number as barcode, MAX(sii.id) as latest_id')
            ->groupBy('sii.barcode_number')
            ->get();

        if ($latestSaleItemIdByBarcode->isEmpty()) {
            return [];
        }

        $latestIds = $latestSaleItemIdByBarcode->pluck('latest_id')->all();

        $returnedItemIds = DB::table('sale_return_items')
            ->whereIn('sale_invoice_item_id', $latestIds)
            ->pluck('sale_invoice_item_id')
            ->unique()
            ->flip() // for isset() checks
            ->all();

        $sold = [];
        foreach ($latestSaleItemIdByBarcode as $row) {
            if (!isset($returnedItemIds[$row->latest_id])) {
                $sold[$row->barcode] = true;
            }
        }

        return $sold;
    }

    /**
     * Barcodes still physically out on an OUTBOUND consignment (sent to
     * another business, not yet back in stock) — same definition
     * InventoryReportController::buildStockInHand() already uses.
     *
     * @return array<string, true> keyed by barcode, for fast isset() checks
     */
    private function outboundConsignmentBarcodes(): array
    {
        return DB::table('consignment_items as ci')
            ->join('consignments as c', 'c.id', '=', 'ci.consignment_id')
            ->where('ci.item_status', 'in_stock')
            ->where('c.direction', 'outbound')
            ->whereNotNull('ci.source_barcode')
            ->distinct()
            ->pluck('ci.source_barcode')
            ->flip()
            ->map(fn () => true)
            ->all();
    }
}
