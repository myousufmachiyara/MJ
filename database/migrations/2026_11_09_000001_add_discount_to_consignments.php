<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Consignment discounts - same two levels as the Sale Invoice:
 *   - consignment_items.discount_*  : item-wise discount (amount | percent).
 *       consignment_items.agreed_value stays THE item's value and is stored
 *       AFTER this discount, so settlement, dashboard and inventory reports
 *       keep working unchanged.
 *   - consignments.discount_*       : overall discount on the whole consignment.
 * Existing rows get no discount (NULL / 0).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('consignment_items') && !Schema::hasColumn('consignment_items', 'discount_amount')) {
            Schema::table('consignment_items', function (Blueprint $table) {
                $table->string('discount_type', 10)->nullable()->after('agreed_value');
                $table->decimal('discount_value', 18, 4)->default(0)->after('discount_type');
                $table->decimal('discount_amount', 18, 2)->default(0)->after('discount_value');
            });
        }

        if (Schema::hasTable('consignments') && !Schema::hasColumn('consignments', 'discount_amount')) {
            Schema::table('consignments', function (Blueprint $table) {
                $table->string('discount_type', 10)->nullable()->after('remarks');
                $table->decimal('discount_value', 18, 4)->default(0)->after('discount_type');
                $table->decimal('discount_amount', 18, 2)->default(0)->after('discount_value');
            });
        }
    }

    public function down(): void
    {
        foreach (['consignment_items', 'consignments'] as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'discount_amount')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropColumn(['discount_type', 'discount_value', 'discount_amount']);
                });
            }
        }
    }
};
