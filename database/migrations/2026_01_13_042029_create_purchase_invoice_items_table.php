<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('purchase_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchase_invoice_id');

            /* ================= PRODUCT ================= */

            $table->string('item_name')->nullable();              // manual name
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('variation_id')->nullable();
            $table->string('item_description')->nullable(); // for custom descriptions

            /* ================= ITEM DETAILS ================= */
            $table->decimal('gross_weight', 15, 3)->default(0);
            $table->decimal('purity', 10, 3)->default(0); // percentage
            $table->decimal('purity_weight', 15, 3)->default(0); // calculated
            $table->decimal('col_995', 15, 3)->default(0);
            $table->decimal('making_rate', 15, 2)->default(0);
            $table->decimal('making_value', 18, 2)->default(0);
            $table->decimal('parts_total', 15, 2)->default(0);
            $table->decimal('material_rate', 18, 2)->default(0); // material cost based on metal_rate
            $table->string('material_type'); // for custom descriptions
            $table->decimal('material_value', 18, 2)->default(0); // total value of metals
            $table->decimal('taxable_amount', 18, 2)->default(0);
            $table->decimal('vat_percent', 5, 2)->default(0); 
            $table->decimal('vat_amount', 18, 2)->default(0); // optional, for direct VAT calculation
            $table->decimal('item_total', 18, 2)->default(0); // optional, for direct VAT calculation

            /* ================= METAL RATES PER ITEM ================= */
            $table->decimal('gold_rate', 18, 2)->nullable();
            $table->decimal('diamond_rate', 18, 2)->nullable();

            /* ================= REMARKS & FILES ================= */
            $table->string('remarks')->nullable();
            $table->string('barcode_number')->nullable()->unique();
            $table->boolean('is_printed')->default(false);
            $table->timestamps();

            $table->foreign('purchase_invoice_id')->references('id')->on('purchase_invoices')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('variation_id')->references('id')->on('product_variations')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_invoice_items');
    }
};
