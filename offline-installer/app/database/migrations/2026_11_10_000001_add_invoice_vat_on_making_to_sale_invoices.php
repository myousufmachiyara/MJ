<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('sale_invoices', function (Blueprint $table) {
            // true = invoice VAT is charged on the making cost only (after discounts)
            $table->boolean('invoice_vat_on_making')->default(false)->after('invoice_vat_amount');
        });
    }

    public function down(): void
    {
        Schema::table('sale_invoices', function (Blueprint $table) {
            $table->dropColumn('invoice_vat_on_making');
        });
    }
};
