<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
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
        } else {
            $this->info('Admin user already exists — password left unchanged.');
        }

        return self::SUCCESS;
    }
}
