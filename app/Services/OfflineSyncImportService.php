<?php

namespace App\Services;

use App\Models\OfflineSyncBatch;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * LIVE SIDE of the offline/live sync feature.
 *
 * Takes a file produced by OfflineSyncExportService::build() (the "Export
 * for Sync" button on an offline install) and inserts its rows into the
 * live database.
 *
 * Why this is safe to just insert, with no ID remapping or merge logic:
 *   - Every row's id comes from the offline install's reserved AUTO_INCREMENT
 *     range (900,000,001+ — see PrepareOfflineDatabase), which can never
 *     collide with a live-generated id.
 *   - Every document number (invoice_no, voucher_no, etc.) was generated
 *     with an "OFF-" prefix, which can never collide with a live-generated
 *     number.
 *   - Rows are inserted in the exact same parent-before-child order they
 *     were exported in (OfflineSyncExportService::TABLES), so every foreign
 *     key a child row points to already exists by the time that child is
 *     inserted — either from an earlier table in this same batch, or from
 *     an earlier sync batch that was already imported.
 *   - Balances and stock status are computed live from these rows on every
 *     page load (see chat — accountNetBalance() etc. have no stored
 *     running total), so nothing needs recalculating afterward; only the
 *     application cache gets cleared, in case anything was cached.
 *
 * Dedupe: the whole import is keyed by the file's batch_id. If that
 * batch_id was already imported (an OfflineSyncBatch row with
 * direction='imported' already exists for it), this is a safe no-op — the
 * client can re-upload the same file by mistake without double-importing.
 *
 * Atomicity: the entire import runs inside one transaction. Any failure —
 * on any table — rolls back everything from this batch; there's no
 * "half-imported" state to clean up by hand.
 */
class OfflineSyncImportService
{
    /**
     * @return array{status: 'imported'|'already_imported', counts?: array<string,int>}
     */
    public function importFromFile(string $path, string $originalFileName): array
    {
        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new \RuntimeException('Could not read the uploaded file.');
        }

        $bundle = json_decode($raw, true);
        if (!is_array($bundle) || empty($bundle['batch_id']) || !isset($bundle['tables']) || !is_array($bundle['tables'])) {
            throw new \RuntimeException('This doesn\'t look like a valid sync file (missing batch_id or tables). '
                . 'Make sure you\'re uploading the exact file "Export for Sync" produced, not a renamed or edited copy.');
        }

        $batchId = (string) $bundle['batch_id'];

        if (OfflineSyncBatch::where('batch_id', $batchId)->where('direction', 'imported')->exists()) {
            return ['status' => 'already_imported'];
        }

        $counts = [];

        DB::transaction(function () use ($bundle, $batchId, $originalFileName, &$counts) {
            foreach (OfflineSyncExportService::TABLES as $table) {
                $rows = $bundle['tables'][$table] ?? null;
                if (empty($rows) || !is_array($rows)) {
                    continue;
                }

                // Insert in reasonable-size chunks rather than one giant
                // statement, so a very large backlog export doesn't risk
                // hitting max_allowed_packet.
                foreach (array_chunk($rows, 200) as $chunk) {
                    DB::table($table)->insert($chunk);
                }

                $counts[$table] = count($rows);
            }

            OfflineSyncBatch::create([
                'batch_id'     => $batchId,
                'direction'    => 'imported',
                'table_counts' => $counts,
                'file_name'    => $originalFileName,
                'created_by'   => auth()->id(),
            ]);
        });

        // Nothing is cached in a stored "balance" sense (see class docblock)
        // — this just clears config/route/view caches in case anything was
        // built with artisan's optimize commands.
        Artisan::call('optimize:clear');

        return ['status' => 'imported', 'counts' => $counts];
    }
}
