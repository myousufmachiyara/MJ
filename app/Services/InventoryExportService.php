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
 * every purchase ever made, and not the full original quantity of
 * something that's been partly sold.
 *
 * PARTIAL WEIGHT — gold is sold in chunks, not as a whole binary item, so
 * "available" is never a yes/no question per barcode; it's a remaining
 * WEIGHT. This deliberately mirrors InventoryReportController's own
 * buildStockInHand() exactly — same formula, same scaling — so this
 * export's numbers always tie out against the live site's own "Stock In
 * Hand" report rather than drifting from it with a second, slightly
 * different definition of "available":
 *
 *   sold_weight      = SUM(gross_weight) of every sale_invoice_item for
 *                      that barcode, whose sale invoice still exists
 *                      (not soft-deleted) — i.e. genuinely sold, exactly
 *                      what POS/live's own report already counts as sold.
 *   outbound_weight  = SUM(gross_weight) of ConsignmentItem rows still
 *                      'in_stock' whose parent Consignment is 'outbound'
 *                      (physically away at a partner's shop).
 *   remaining_weight = purchased_gross_weight - sold_weight - outbound_weight
 *
 * An item with remaining_weight <= 0 is fully consumed and dropped from
 * the export entirely. A PARTIALLY sold item is kept, but its weight and
 * value fields are scaled down to the remaining fraction before export
 * (ratio = remaining_weight / purchased_weight) — same fields
 * buildStockInHand() itself scales: gross_weight, net_weight,
 * purity_weight, col_995, making_value, material_value, vat_amount,
 * item_total. This table is a disposable, fully-replaced snapshot on the
 * offline side (never the historical record of what was originally
 * purchased — that stays intact on live), so it's safe and correct for
 * the exported copy to represent "what's actually left right now," not
 * the original purchased quantity; without this, an item live has
 * already partly sold would show as fully available again offline,
 * risking overselling the same physical weight twice.
 *
 * NOTE: buildStockInHand() does not currently credit sale returns back
 * into sold_weight (no sale_return_items offset), so this export matches
 * that as-is for now, to keep the two numbers comparable. If that should
 * change, it needs to change in both places together, not just here.
 *
 * A purchase item with no barcode_number at all can't be matched against
 * either lookup, so it's kept at its full original weight (safer default
 * — never hide real inventory just because we can't prove it's gone).
 *
 * purchase_invoice_item_parts (diamonds/stones attached to an item) are
 * NOT scaled, matching buildStockInHand()'s own choice to leave them
 * untouched — only filtered down to parts whose parent item survived.
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
                $soldWeightByBarcode     = $this->soldWeightByBarcode();
                $outboundWeightByBarcode = $this->outboundWeightByBarcode();

                $kept = [];
                foreach ($query->get() as $row) {
                    $arr         = (array) $row;
                    $barcode     = $arr['barcode_number'] ?? null;
                    $purchasedWt = (float) ($arr['gross_weight'] ?? 0);
                    $soldWt      = $barcode !== null ? (float) ($soldWeightByBarcode[$barcode] ?? 0) : 0;
                    $outboundWt  = $barcode !== null ? (float) ($outboundWeightByBarcode[$barcode] ?? 0) : 0;
                    $remainingWt = $purchasedWt - $soldWt - $outboundWt;

                    if ($remainingWt <= 0.0001) {
                        continue; // fully consumed — drop entirely, same as buildStockInHand()
                    }

                    $ratio = $purchasedWt > 0 ? $remainingWt / $purchasedWt : 1;
                    if ($ratio < 1) {
                        foreach (['gross_weight', 'net_weight', 'purity_weight', 'col_995',
                                  'making_value', 'material_value', 'vat_amount', 'item_total'] as $field) {
                            if (array_key_exists($field, $arr) && $arr[$field] !== null) {
                                $arr[$field] = (float) $arr[$field] * $ratio;
                            }
                        }
                    }

                    $kept[] = $arr;
                }

                $bundle['tables'][$table] = $kept;
                $counts[$table] = count($kept);
                $availableItemIds = array_column($kept, 'id');
                continue; // already assembled $kept directly — skip the generic assembly below
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
     * SUM(gross_weight) of every sale_invoice_item per barcode, counting
     * only sales whose invoice still exists (not soft-deleted) — exactly
     * InventoryReportController::buildStockInHand()'s $soldWeightByBarcode.
     *
     * @return array<string, float> keyed by barcode
     */
    private function soldWeightByBarcode(): array
    {
        return DB::table('sale_invoice_items as sii')
            ->join('sale_invoices as si', 'si.id', '=', 'sii.sale_invoice_id')
            ->whereNull('si.deleted_at')
            ->whereNotNull('sii.barcode_number')
            ->selectRaw('sii.barcode_number as barcode, SUM(sii.gross_weight) as total')
            ->groupBy('sii.barcode_number')
            ->pluck('total', 'barcode')
            ->map(fn ($v) => (float) $v)
            ->all();
    }

    /**
     * SUM(gross_weight) of ConsignmentItem rows still 'in_stock' whose
     * parent Consignment is 'outbound', per source_barcode — exactly
     * buildStockInHand()'s $outboundWeightByBarcode.
     *
     * @return array<string, float> keyed by barcode
     */
    private function outboundWeightByBarcode(): array
    {
        return DB::table('consignment_items as ci')
            ->join('consignments as c', 'c.id', '=', 'ci.consignment_id')
            ->where('ci.item_status', 'in_stock')
            ->where('c.direction', 'outbound')
            ->whereNotNull('ci.source_barcode')
            ->selectRaw('ci.source_barcode as barcode, SUM(ci.gross_weight) as total')
            ->groupBy('ci.source_barcode')
            ->pluck('total', 'barcode')
            ->map(fn ($v) => (float) $v)
            ->all();
    }
}