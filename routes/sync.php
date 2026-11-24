<?php

use App\Http\Controllers\SyncController;
use Illuminate\Support\Facades\Route;

/**
 * Offline/live sync routes — kept in their own file (instead of
 * routes/web.php) and registered separately via bootstrap/app.php's
 * withRouting(then: ...), purely so this work never has to touch
 * routes/web.php, which currently has unrelated pending edits of your own.
 * Functionally identical to being declared directly in web.php — same 'web'
 * middleware group, same auth, just a different file.
 */
Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/sync', [SyncController::class, 'index'])->name('sync.index');
    Route::post('/sync/export', [SyncController::class, 'export'])->name('sync.export');
    Route::post('/sync/import', [SyncController::class, 'import'])->name('sync.import');
});
