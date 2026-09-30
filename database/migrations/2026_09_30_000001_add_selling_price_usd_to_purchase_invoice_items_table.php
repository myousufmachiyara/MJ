<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FEATURE (bulk Selling Price import, AED + USD): the existing
 * `selling_price` column (added in 2026_09_25_071855_...) is kept exactly
 * as-is and continues to mean AED — nothing that already reads/writes it
 * (PurchaseInvoiceController::updateSellingPrice(), the Purchase Invoice
 * create/edit screens, SaleInvoiceController::posScan()) changes or needs
 * to change. This adds a second, independent nullable column for a USD
 * price, purely additive, so a shop selling in either currency can record
 * both without one being derived/converted from the other (no fixed
 * exchange rate is assumed — these are two manually-set, independent
 * prices, same "not yet priced = null, never silently 0" reasoning as the
 * original column).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_invoice_items', function (Blueprint $table) {
            $table->decimal('selling_price_usd', 18, 2)->nullable()->after('selling_price');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_invoice_items', function (Blueprint $table) {
            $table->dropColumn('selling_price_usd');
        });
    }
};
