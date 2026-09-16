<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds category_id / subcategory_id to purchase_invoice_items so each
     * item can be tagged with the Product Category / Subcategory it belongs
     * to (selected on the Purchase Invoice item row, cascading from Category
     * to Subcategory).
     *
     * The subcategory's `code` (product_subcategories.code) is what the new
     * barcode_number format is generated from: {SubcategoryCode}-{sequence}.
     * See PurchaseInvoiceController::generateSubcategoryBarcodeNumber().
     */
    public function up(): void
    {
        Schema::table('purchase_invoice_items', function (Blueprint $table) {
            $table->unsignedBigInteger('category_id')->nullable()->after('product_id');
            $table->unsignedBigInteger('subcategory_id')->nullable()->after('category_id');

            $table->foreign('category_id')->references('id')->on('product_categories')->onDelete('set null');
            $table->foreign('subcategory_id')->references('id')->on('product_subcategories')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_invoice_items', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropForeign(['subcategory_id']);
            $table->dropColumn(['category_id', 'subcategory_id']);
        });
    }
};