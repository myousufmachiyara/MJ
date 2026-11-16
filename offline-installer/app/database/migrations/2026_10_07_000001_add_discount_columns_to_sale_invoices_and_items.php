<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FEATURE (Sale Invoice discount): item-wise and whole-invoice discount.
 *
 * Same three columns on both tables:
 *   discount_type    'amount' | 'percent' — how the user entered it (null = none)
 *   discount_value   what was typed (the flat amount, or the percentage) — kept
 *                    so the edit screen can show it exactly as entered
 *   discount_amount  the actual discount in the invoice's currency, after
 *                    resolving a percentage and capping at the discountable amount
 *
 * Purely additive and nullable/zero by default: every existing invoice reads
 * as "no discount" and nothing about its totals or accounting changes. Guarded
 * with hasColumn() so re-running on a database that already has them is safe.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['sale_invoices', 'sale_invoice_items'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (!Schema::hasColumn($tableName, 'discount_type')) {
                    $table->string('discount_type', 10)->nullable();
                }
                if (!Schema::hasColumn($tableName, 'discount_value')) {
                    $table->decimal('discount_value', 18, 4)->default(0);
                }
                if (!Schema::hasColumn($tableName, 'discount_amount')) {
                    $table->decimal('discount_amount', 18, 2)->default(0);
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['sale_invoices', 'sale_invoice_items'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                foreach (['discount_type', 'discount_value', 'discount_amount'] as $col) {
                    if (Schema::hasColumn($tableName, $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
