<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\OfflineSyncExportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * OFFLINE INSTALLER: runs the normal database seeder (admin/manager users,
 * roles, chart of accounts, purities, categories, ...) and then — only on
 * a brand-new install — replaces the seeder's hardcoded admin password
 * with a random one, so every client installation gets its own password
 * instead of everyone sharing "12345678". The password is written once to
 * CREDENTIALS.txt in the app folder.
 *
 * Safe to re-run: if an admin user already exists, it only re-runs the
 * seeder (which uses firstOrCreate throughout) and leaves passwords alone.
 *
 * FOUND DURING THE SYNC FEATURE'S END-TO-END TEST: every fresh install (the
 * live server included) runs this exact same seeder, which means its output
 * — chart of accounts, head/sub-head of accounts, measurement units,
 * product categories, purities, etc. — is identical reference data on both
 * sides already, NOT something that should ever flow through a sync export.
 * Without marking it as already-synced here, the very first "Export for
 * Sync" on a brand-new offline install would try to re-send all of it into
 * the live database and collide. So: on a fresh install only, right after
 * seeding, every row that exists at that moment across sync-eligible tables
 * (OfflineSyncExportService::TABLES) gets synced_at stamped — the sync
 * feature then only ever sees rows created by real usage afterward.
 */
class SeedOfflineAdmin extends Command
{
    protected $signature   = 'app:seed-offline-admin';
    protected $description = 'Seed default data for a fresh offline install and set a random admin password';

    public function handle(): int
    {
        $isFreshInstall = !User::where('username', 'admin')->exists();

        $this->call('db:seed');

        if ($isFreshInstall) {
            $password = Str::password(12, symbols: false);
            User::where('username', 'admin')->update(['password' => Hash::make($password)]);

            $path = base_path('CREDENTIALS.txt');
            file_put_contents($path,
                "MJ Jewelry System — login details\n" .
                "==================================\n\n" .
                "Username: admin\n" .
                "Password: {$password}\n\n" .
                "Please log in and change this password right away, then delete this file.\n"
            );

            $this->info("Admin password generated — see CREDENTIALS.txt in the app folder.");

            if (config('app.offline_mode')) {
                $this->markSeedDataAsAlreadySynced();
            }
        } else {
            $this->info('Admin user already exists — password left unchanged.');
        }

        return self::SUCCESS;
    }

    /**
     * Stamp synced_at on every row the seeder just created, so "Export for
     * Sync" never picks up this install's own copy of shared reference
     * data. Only runs once, on a fresh install, right after seeding — never
     * on a re-run (which would wrongly mark real usage data as synced too).
     */
    private function markSeedDataAsAlreadySynced(): void
    {
        $now = now();
        foreach (OfflineSyncExportService::TABLES as $table) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'synced_at')) {
                continue;
            }
            DB::table($table)->whereNull('synced_at')->update(['synced_at' => $now]);
        }
        $this->info('Seed data marked as already synced — sync will only pick up new usage from here.');
    }
}
