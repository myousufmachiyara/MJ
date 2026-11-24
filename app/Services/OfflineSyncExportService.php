<?php

namespace App\Services;

use App\Models\OfflineSyncBatch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * OFFLINE SIDE of the offline/live sync feature.
 *
 * "Export for Sync" (SyncController::export) calls build() to bundle every
 * row that hasn't been exported yet (synced_at IS NULL) across all
 * sync-eligible tables into one JSON file, then marks those rows as
 * exported so the next export is incremental — only new/changed data goes
 * out each time.
 *
 * The table list below is in strict parent-before-child order. That's not
 * needed for the export itself (it's just a snapshot read), but the Import
 * step on the live side (task #13) re-uses this exact order to INSERT rows
 * parent-first, which satisfies every foreign key without any special
 * handling. Keep this order in sync with
 * App\Console\Commands\PrepareOfflineDatabase::RESERVED_TABLES and the
 * 2026_11_23_000001 migration if that list ever changes.
 *
 * NOT exported: `users`. A new offline staff login carries a password hash
 * that would become valid on the live site too the moment it's imported —
 * that's a judgment call deliberately left out of the data sync for now
 * rather than decided silently. Ask before adding it.
 *
 * NOT exported (don't exist offline at all): purchase_invoices,
 * purchase_returns and their item/parts/attachment tables, plus inbound
 * Consignments — see BlockOfflineRestrictedModules / ConsignmentController.
 */
class OfflineSyncExportService
{
    public const TABLES = [
        // Master data first — everything else has a foreign key into these.
        'head_of_accounts', 'sub_head_of_accounts', 'chart_of_accounts',
        'measurement_units',
        'product_categories', 'product_subcategories',
        'products', 'product_variations',
        'attributes', 'attribute_values', 'product_variation_attribute_values',
        'product_images', 'product_parts', 'purities',

        // Sales-side transactions
        'sale_invoices', 'sale_invoice_items', 'sale_invoice_item_parts', 'sale_invoice_attachments',
        'sale_returns', 'sale_return_items', 'sale_return_item_parts',
        'quotations', 'quotation_items', 'quotation_item_parts', 'quotation_attachments',
        'consignments', 'consignment_items', 'consignment_item_parts',
        'vouchers', 'accounting_entries',
    ];

    /**
     * Build the export bundle. Returns null if there's nothing new to send.
     *
     * @return array{batchId: string, path: string, fileName: string, counts: array<string,int>}|null
     */
    public function build(): ?array
    {
        if (!config('app.offline_mode')) {
            throw new \RuntimeException('Export for Sync only runs on an offline install (config("app.offline_mode") is not true).');
        }

        $batchId = (string) Str::uuid();
        $now     = now();

        $bundle = [
            'batch_id'    => $batchId,
            'exported_at' => $now->toIso8601String(),
            'tables'      => [],
        ];
        $counts   = [];
        $rowIdsByTable = [];
        $hasAnyRows = false;

        DB::beginTransaction();
        try {
            foreach (self::TABLES as $table) {
                $query = DB::table($table)->whereNull('synced_at');

                // Deleting something while offline isn't synced — only
                // currently-active rows go out. (Deleting a record that was
                // already synced to live, and making that deletion itself
                // propagate, isn't handled yet — that's a separate feature
                // from "new data flows up," and was never asked for.)
                if (Schema::hasColumn($table, 'deleted_at')) {
                    $query->whereNull('deleted_at');
                }

                $rows = $query->get();

                if ($rows->isEmpty()) {
                    continue;
                }

                $hasAnyRows = true;
                $bundle['tables'][$table] = $rows->map(fn ($row) => (array) $row)->all();
                $counts[$table] = $rows->count();
                $rowIdsByTable[$table] = $rows->pluck('id')->all();
            }

            if (!$hasAnyRows) {
                DB::rollBack();
                return null;
            }

            // Mark every exported row so the next export doesn't re-send it.
            foreach ($rowIdsByTable as $table => $ids) {
                DB::table($table)->whereIn('id', $ids)->update([
                    'synced_at'     => $now,
                    'sync_batch_id' => $batchId,
                ]);
            }

            $fileName = 'mj-sync-' . $now->format('Y-m-d_His') . '-' . substr($batchId, 0, 8) . '.json';
            $dir      = storage_path('app/sync_exports');
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            $path = $dir . DIRECTORY_SEPARATOR . $fileName;
            file_put_contents($path, json_encode($bundle, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            OfflineSyncBatch::create([
                'batch_id'     => $batchId,
                'direction'    => 'exported',
                'table_counts' => $counts,
                'file_name'    => $fileName,
                'created_by'   => auth()->id(),
            ]);

            DB::commit();

            return [
                'batchId'  => $batchId,
                'path'     => $path,
                'fileName' => $fileName,
                'counts'   => $counts,
            ];
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
