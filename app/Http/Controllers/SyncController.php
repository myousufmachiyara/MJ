<?php

namespace App\Http\Controllers;

use App\Models\OfflineSyncBatch;
use App\Services\InventoryExportService;
use App\Services\InventoryImportService;
use App\Services\OfflineSyncExportService;
use App\Services\OfflineSyncImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;

/**
 * OFFLINE/LIVE SYNC — admin screen.
 *
 * On an offline install (config('app.offline_mode') === true): shows the
 * "Export for Sync" button and this install's export history.
 *
 * On the live site: this same screen will show the "Import Offline Data"
 * upload form instead (task #13, not built yet) and the import history.
 *
 * Restricted to super-admins — see guardSuperAdmin(). This touches raw
 * financial/ledger data directly, so it isn't a normal permission-gated
 * module like the rest of the app.
 */
class SyncController extends Controller
{
    private function guardSuperAdmin(): void
    {
        $user = auth()->user();
        if (!$user || (!($user->id === 1) && !$user->hasRole('super-admin'))) {
            abort(403, 'Only a super-admin can access Sync.');
        }
    }

    public function index()
    {
        $this->guardSuperAdmin();

        $history = OfflineSyncBatch::orderByDesc('id')->limit(20)->get();

        return view('sync.index', [
            'isOfflineMode' => (bool) config('app.offline_mode'),
            'history'       => $history,
        ]);
    }

    /**
     * OFFLINE SIDE ONLY. Builds the export bundle and streams it to the
     * browser as a download — nothing stays only on disk, since the client
     * needs the file in hand (USB drive / email) to carry it to the live
     * site.
     */
    public function export(Request $request)
    {
        $this->guardSuperAdmin();

        if (!config('app.offline_mode')) {
            return back()->with('error', 'Export for Sync is only available on an offline install.');
        }

        $result = (new OfflineSyncExportService())->build();

        if ($result === null) {
            return back()->with('info', 'Nothing new to export — everything has already been synced.');
        }

        return Response::download($result['path'], $result['fileName'])->deleteFileAfterSend(false);
    }

    /**
     * LIVE SIDE ONLY. Client uploads a file "Export for Sync" produced on
     * their offline install; this inserts its rows here. Safe to re-upload
     * the same file twice — see OfflineSyncImportService's dedupe.
     */
    public function import(Request $request)
    {
        $this->guardSuperAdmin();

        if (config('app.offline_mode')) {
            return back()->with('error', 'Import Offline Data only runs on the live site, not on an offline install.');
        }

        $request->validate([
            'sync_file' => 'required|file',
        ]);

        $file = $request->file('sync_file');

        try {
            $result = (new OfflineSyncImportService())->importFromFile(
                $file->getRealPath(),
                $file->getClientOriginalName()
            );
        } catch (\Throwable $e) {
            Log::error('Sync import failed — ' . $e->getMessage(), [
                'file' => $e->getFile(), 'line' => $e->getLine(),
            ]);
            return back()->with('error', 'Import failed: ' . $e->getMessage());
        }

        if ($result['status'] === 'already_imported') {
            return back()->with('info', 'Already imported — nothing new in this file.');
        }

        $total   = array_sum($result['counts']);
        $summary = collect($result['counts'])->map(fn ($c, $t) => "$t: $c")->implode(', ');

        return back()->with('success', "Imported {$total} record(s). ({$summary})");
    }

    /**
     * LIVE SIDE ONLY. The "permanent" replacement for the manual phpMyAdmin
     * export + refresh-inventory.bat process — see InventoryExportService
     * for the full background. Streams a full snapshot of the 7 purchase
     * tables to the browser as a download.
     */
    public function exportInventory(Request $request)
    {
        $this->guardSuperAdmin();

        if (config('app.offline_mode')) {
            return back()->with('error', 'Export Inventory is only available on the live site.');
        }

        $result = (new InventoryExportService())->build();

        return Response::download($result['path'], $result['fileName'])->deleteFileAfterSend(false);
    }

    /**
     * OFFLINE SIDE ONLY. Client uploads a file the live site's "Export
     * Inventory" button produced; this fully replaces this install's copy
     * of the 7 purchase tables with what's in it. Safe to re-upload the
     * same file twice (recognized and skipped), and safe to run as often
     * as needed in general — see InventoryImportService's class docblock
     * for why a full replace is the correct, safe behavior here.
     */
    public function importInventory(Request $request)
    {
        $this->guardSuperAdmin();

        if (!config('app.offline_mode')) {
            return back()->with('error', 'Import Inventory only runs on an offline install, not on the live site.');
        }

        $request->validate([
            'inventory_file' => 'required|file',
        ]);

        $file = $request->file('inventory_file');

        try {
            $result = (new InventoryImportService())->importFromFile(
                $file->getRealPath(),
                $file->getClientOriginalName()
            );
        } catch (\Throwable $e) {
            Log::error('Inventory import failed — ' . $e->getMessage(), [
                'file' => $e->getFile(), 'line' => $e->getLine(),
            ]);
            return back()->with('error', 'Import failed: ' . $e->getMessage());
        }

        if ($result['status'] === 'already_imported') {
            return back()->with('info', 'Already imported — nothing new in this file.');
        }

        $total   = array_sum($result['counts']);
        $summary = collect($result['counts'])->map(fn ($c, $t) => "$t: $c")->implode(', ');

        return back()->with('success', "Inventory refreshed — {$total} record(s). ({$summary})");
    }
}