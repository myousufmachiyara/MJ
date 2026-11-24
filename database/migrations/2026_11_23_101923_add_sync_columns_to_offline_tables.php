<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Offline/live sync — adds synced_at + sync_batch_id to every table that
 * "Export for Sync" can pull rows from (see
 * App\Services\OfflineSyncExportService::SYNCABLE_TABLES for the exact list
 * and the reasoning behind it — same tables as
 * App\Console\Commands\PrepareOfflineDatabase::RESERVED_TABLES, minus
 * `users`, which isn't part of the data sync for now — see chat).
 *
 * These columns exist on BOTH the offline and the live schema (migrations
 * run the same way everywhere), but only ever get populated on an offline
 * install:
 *   - synced_at:     null = "not yet exported"; set the moment a row is
 *                    included in an export, so the next export never
 *                    re-sends it (this is what makes sync incremental).
 *   - sync_batch_id: which export batch a row went out in, for
 *                    troubleshooting ("which sync brought this invoice in").
 *
 * On the live server these two columns simply stay null forever on
 * naturally-created rows — harmless, no behavior depends on them there
 * except the Import step setting them on inserted offline rows.
 */
return new class extends Migration
{
    private const TABLES = [
        'sale_invoices', 'sale_invoice_items', 'sale_invoice_item_parts', 'sale_invoice_attachments',
        'sale_returns', 'sale_return_items', 'sale_return_item_parts',
        'quotations', 'quotation_items', 'quotation_item_parts', 'quotation_attachments',
        'consignments', 'consignment_items', 'consignment_item_parts',
        'vouchers', 'accounting_entries',
        'chart_of_accounts', 'head_of_accounts', 'sub_head_of_accounts',
        'products', 'product_categories', 'product_subcategories',
        'product_variations', 'product_variation_attribute_values',
        'attributes', 'attribute_values', 'product_images', 'product_parts',
        'measurement_units', 'purities',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) use ($table) {
                if (!Schema::hasColumn($table, 'synced_at')) {
                    $t->timestamp('synced_at')->nullable()->index();
                }
                if (!Schema::hasColumn($table, 'sync_batch_id')) {
                    $t->string('sync_batch_id', 36)->nullable()->index();
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) use ($table) {
                if (Schema::hasColumn($table, 'synced_at')) {
                    $t->dropColumn('synced_at');
                }
                if (Schema::hasColumn($table, 'sync_batch_id')) {
                    $t->dropColumn('sync_batch_id');
                }
            });
        }
    }
};
