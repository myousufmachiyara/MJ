<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remembers the selling price an item line was priced at (invoice / quotation
 * currency). NULL = the line was priced by rate x weight + making + parts
 * (every existing row), so old documents are untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['sale_invoice_items', 'quotation_items'] as $tableName) {
            if (Schema::hasTable($tableName) && !Schema::hasColumn($tableName, 'selling_price')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->decimal('selling_price', 18, 2)->nullable()->after('item_total');
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['sale_invoice_items', 'quotation_items'] as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'selling_price')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropColumn('selling_price');
                });
            }
        }
    }
};
