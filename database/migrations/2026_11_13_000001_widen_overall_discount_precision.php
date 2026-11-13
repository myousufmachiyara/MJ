<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The OVERALL (document-level) discount lives on sale_invoices, quotations and
 * consignments. Widen it so values with more than 2 decimals are kept exactly:
 *   discount_value  (what was typed: amount or %)  decimal(18,4) -> decimal(18,6)
 *   discount_amount (resulting discount amount)    decimal(18,2) -> decimal(18,4)
 * Item-level discount columns are unchanged.
 */
return new class extends Migration {
    private array $tables = ['sale_invoices', 'quotations', 'consignments'];

    public function up(): void
    {
        foreach ($this->tables as $t) {
            if (!Schema::hasTable($t)) continue;
            Schema::table($t, function (Blueprint $table) use ($t) {
                if (Schema::hasColumn($t, 'discount_value'))  $table->decimal('discount_value', 18, 6)->default(0)->change();
                if (Schema::hasColumn($t, 'discount_amount')) $table->decimal('discount_amount', 18, 4)->default(0)->change();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $t) {
            if (!Schema::hasTable($t)) continue;
            Schema::table($t, function (Blueprint $table) use ($t) {
                if (Schema::hasColumn($t, 'discount_value'))  $table->decimal('discount_value', 18, 4)->default(0)->change();
                if (Schema::hasColumn($t, 'discount_amount')) $table->decimal('discount_amount', 18, 2)->default(0)->change();
            });
        }
    }
};
