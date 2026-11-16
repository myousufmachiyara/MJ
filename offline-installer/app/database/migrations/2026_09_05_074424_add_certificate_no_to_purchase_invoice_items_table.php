<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds a per-item certificate number (e.g. GIA/IGI diamond certificate #)
     * so it can be printed on the item's thermal barcode label. Purely
     * informational — not used in any pricing/accounting calculation.
     */
    public function up(): void
    {
        Schema::table('purchase_invoice_items', function (Blueprint $table) {
            $table->string('certificate_no')->nullable()->after('barcode_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_invoice_items', function (Blueprint $table) {
            $table->dropColumn('certificate_no');
        });
    }
};
