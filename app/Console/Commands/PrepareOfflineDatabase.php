<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * OFFLINE INSTALLER ONLY — runs once during first-run.bat, right after
 * migrations and before seeding.
 *
 * PURPOSE
 * Every offline installation runs its own separate MariaDB database. If both
 * it and the live mj.billtrix.com database auto-increment their primary keys
 * from 1, 2, 3..., an offline-created row and an unrelated live row can end
 * up sharing the same id — and every foreign key pointing at either of them
 * (sale_invoice_items.sale_invoice_id, accounting_entries.voucher_id, etc.)
 * becomes ambiguous the moment offline data is later imported into live.
 *
 * The fix: reserve a high, non-overlapping AUTO_INCREMENT starting point
 * (OFFLINE_ID_START) on every table that can be written to on an offline
 * install. The live server will realistically never reach that many rows,
 * so offline IDs can never collide with live ones — and the eventual import
 * can insert offline rows with their original ids completely unchanged, with
 * every foreign key relationship still correct, no remapping needed.
 *
 * SAFETY
 * This only ever touches a table that is still completely empty (checked
 * before every ALTER). It refuses to run at all unless
 * config('app.offline_mode') is true, so it can never be run by accident
 * against the live database. It's also safe to re-run — already-empty
 * tables just get the same AUTO_INCREMENT set again; non-empty ones are
 * left alone and reported.
 *
 * SCOPE — tables included here, and why:
 *   - Sales-side transactions: Sales Invoices, Sale Returns, Quotations,
 *     Consignments (outbound only — inbound is blocked offline separately;
 *     see ConsignmentController::validateConsignment()), Vouchers,
 *     Accounting Entries, and every child/attachment/parts table under them.
 *   - Master data that can still be created through the normal UI while
 *     offline: customers/vendors/banks (chart_of_accounts and its parents),
 *     products and their categories/variations/attributes, measurement
 *     units, purities.
 *   - users — in case a client ever adds a staff login while offline.
 *
 * DELIBERATELY EXCLUDED — Purchase Invoices, Purchase Return, and their
 * item/parts/attachment tables. Purchasing creates brand-new barcoded stock;
 * doing that offline risks two separately-numbered copies of the same
 * physical item that can't be reconciled after the fact. These modules are
 * simply unavailable in an offline build (see
 * App\Http\Middleware\BlockOfflineRestrictedModules) and never need a
 * reserved range.
 *
 * Also excluded: framework-only tables (cache, jobs, sessions, migrations,
 * the permission package's own tables) — these aren't created through
 * normal offline usage, and the permission tables in particular are seeded
 * config that should stay identical across every install.
 */
class PrepareOfflineDatabase extends Command
{
    protected $signature   = 'app:prepare-offline-db';
    protected $description = 'Reserve a high AUTO_INCREMENT range on every offline-syncable table (offline builds only)';

    /**
     * Every offline-created row's id starts here. 900 million rows in any
     * one of these tables, on the live server, is not a realistic scenario.
     */
    private const OFFLINE_ID_START = 900_000_001;

    /**
     * Tables that need the reserved range. See the class docblock above for
     * the reasoning behind this exact list.
     */
    private const RESERVED_TABLES = [
        // Sales-side transactions
        'sale_invoices', 'sale_invoice_items', 'sale_invoice_item_parts', 'sale_invoice_attachments',
        'sale_returns', 'sale_return_items', 'sale_return_item_parts',
        'quotations', 'quotation_items', 'quotation_item_parts', 'quotation_attachments',
        'consignments', 'consignment_items', 'consignment_item_parts',
        'vouchers', 'accounting_entries',

        // Master data creatable through the normal UI
        'chart_of_accounts', 'head_of_accounts', 'sub_head_of_accounts',
        'products', 'product_categories', 'product_subcategories',
        'product_variations', 'product_variation_attribute_values',
        'attributes', 'attribute_values', 'product_images', 'product_parts',
        'measurement_units', 'purities',

        // Staff logins
        'users',
    ];

    public function handle(): int
    {
        if (!config('app.offline_mode')) {
            $this->error('Refusing to run: config("app.offline_mode") is not true. '
                . 'This command only ever runs on an offline-installer build '
                . '(OFFLINE_MODE=true in .env) — never against the live database.');
            return self::FAILURE;
        }

        $this->info('Reserving offline ID range (starting at ' . number_format(self::OFFLINE_ID_START) . ') ...');

        $skipped = [];

        foreach (self::RESERVED_TABLES as $table) {
            if (!Schema::hasTable($table)) {
                $this->warn("  - {$table}: table doesn't exist, skipping.");
                continue;
            }

            $rowCount = (int) DB::table($table)->count();

            if ($rowCount > 0) {
                // Never touch a table that already has data — this command
                // is only meant for the moment right after a fresh install's
                // migrations run, before any real row exists.
                $skipped[] = $table;
                $this->warn("  - {$table}: already has {$rowCount} row(s), left untouched.");
                continue;
            }

            DB::statement("ALTER TABLE `{$table}` AUTO_INCREMENT = " . self::OFFLINE_ID_START);
            $this->line("  - {$table}: OK");
        }

        if (!empty($skipped)) {
            $this->newLine();
            $this->warn('Some tables already had data and were left alone: ' . implode(', ', $skipped) . '.');
            $this->warn('This is expected on a re-run of an already-set-up install. '
                . 'If this is supposed to be a FRESH install and you see this warning, '
                . 'something seeded or created data before this command ran — check '
                . 'first-run.bat\'s step order before using this install with a real client.');
        }

        $this->newLine();
        $this->info('Done.');

        return self::SUCCESS;
    }
}
