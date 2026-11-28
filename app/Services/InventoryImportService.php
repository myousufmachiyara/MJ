<?php

namespace App\Services;

use App\Models\OfflineSyncBatch;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * OFFLINE SIDE of the inventory pull feature — see InventoryExportService
 * for the full background on why this exists.
 *
 * Unlike OfflineSyncImportService (which only ever INSERTs new rows),
 * this FULLY REPLACES its 7 tables every time: deletes every existing row
 * (child-before-parent, so foreign keys never block the delete) then
 * inserts everything from the file (parent-before-child, so foreign keys
 * never block the insert). That's deliberate and safe — nothing offline-
 * only ever lives in these tables, since purchases can't be created
 * offline at all (BlockOfflineRestrictedModules), so there is nothing to
 * lose by replacing them wholesale on every pull.
 *
 * This runs entirely through Eloquent/PDO against the already-correct
 * InnoDB tables Laravel's own migrations created — unlike the earlier
 * mysqldump + manual import route (refresh-inventory.bat), there is no
 * storage-engine mismatch possible here, since no CREATE TABLE statement
 * from an external dump is ever involved.
 */
class InventoryImportService
{
    /**
     * @return array{status: 'imported'|'already_imported', counts?: array<string,int>}
     */
    public function importFromFile(string $path, string $originalFileName): array
    {
        if (!config('app.offline_mode')) {
            throw new \RuntimeException('Import Inventory only runs on an offline install, not on the live site.');
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new \RuntimeException('Could not read the uploaded file.');
        }

        $bundle = json_decode($raw, true);
        if (!is_array($bundle) || empty($bundle['batch_id']) || !isset($bundle['tables']) || !is_array($bundle['tables'])) {
            throw new \RuntimeException('This doesn\'t look like a valid inventory file (missing batch_id or tables). '
                . 'Make sure you\'re uploading the exact file "Export Inventory for Offline" produced, not a renamed or edited copy.');
        }

        $batchId = (string) $bundle['batch_id'];

        if (OfflineSyncBatch::where('batch_id', $batchId)->where('direction', 'inv_import')->exists()) {
            return ['status' => 'already_imported'];
        }

        $counts = [];

        DB::transaction(function () use ($bundle, $batchId, $originalFileName, &$counts) {
            // Child-before-parent delete — reverse of InventoryExportService::TABLES.
            foreach (array_reverse(InventoryExportService::TABLES) as $table) {
                DB::table($table)->delete();
            }

            // Parent-before-child insert.
            foreach (InventoryExportService::TABLES as $table) {
                $rows = $bundle['tables'][$table] ?? null;
                if (empty($rows) || !is_array($rows)) {
                    $counts[$table] = 0;
                    continue;
                }

                // Chunked, same reasoning as OfflineSyncImportService — avoid
                // hitting max_allowed_packet on a very large table.
                foreach (array_chunk($rows, 200) as $chunk) {
                    DB::table($table)->insert($chunk);
                }

                $counts[$table] = count($rows);
            }

            OfflineSyncBatch::create([
                'batch_id'     => $batchId,
                'direction'    => 'inv_import',
                'table_counts' => $counts,
                'file_name'    => $originalFileName,
                'created_by'   => auth()->id(),
            ]);
        });

        Artisan::call('optimize:clear');

        return ['status' => 'imported', 'counts' => $counts];
    }
}
