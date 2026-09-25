<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * FEATURE (Sale Invoice POS): a purchased item IS this app's sellable
     * product — there is no separate Product Master the POS draws from (see
     * PurchaseInvoiceController's class-level docs and
     * PurchaseInvoiceItem::getScanCodeAttribute()). The POS needs a flat,
     * manually-set retail price per purchased item that is completely
     * independent of the existing costing columns (purity/net_weight/
     * making_rate/material_rate/vat_percent/item_total etc.) — none of
     * those feed into it and it never feeds into them. It is optional: a
     * shop may decide the price at purchase time, or leave it blank and set
     * it later from the Purchase Invoice edit screen, any time before the
     * item is sold through the POS.
     *
     * Nullable/no default so "not yet priced" (null) is distinguishable
     * from "priced at zero" (0) — SaleInvoiceController::posScan() checks
     * specifically for null and refuses to add an unpriced item to a POS
     * sale rather than silently selling it for 0.
     */
    public function up(): void
    {
        Schema::table('purchase_invoice_items', function (Blueprint $table) {
            $table->decimal('selling_price', 18, 2)->nullable()->after('item_total');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_invoice_items', function (Blueprint $table) {
            $table->dropColumn('selling_price');
        });
    }
};
