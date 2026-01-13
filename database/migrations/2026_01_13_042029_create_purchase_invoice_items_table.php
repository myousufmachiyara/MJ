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
            $table->foreignId('purchase_invoice_id')->constrained('purchase_invoices')->onDelete('cascade');

            /* ================= PRODUCT ================= */
            $table->foreignId('product_id')->nullable()->constrained('products')->onDelete('set null');
            $table->string('item_description')->nullable(); // for custom descriptions

            /* ================= ITEM DETAILS ================= */
            $table->decimal('gross_weight', 15, 3)->default(0);
            $table->decimal('purity', 10, 3)->default(0); // percentage
            $table->decimal('purity_weight', 15, 3)->default(0); // calculated
            $table->decimal('making_rate', 15, 2)->default(0);
            $table->decimal('material_value', 18, 2)->default(0); // material cost based on metal_rate
            $table->decimal('metal_value', 18, 2)->default(0); // total value of metals
            $table->decimal('taxable_amount', 18, 2)->default(0);
            $table->decimal('vat_percent', 5, 2)->default(0); 
            $table->decimal('vat_amount', 18, 2)->default(0); // optional, for direct VAT calculation

            /* ================= METAL RATES PER ITEM ================= */
            $table->decimal('gold_rate', 18, 2)->nullable();
            $table->decimal('silver_rate', 18, 2)->nullable();
            $table->decimal('other_metal_rate', 18, 2)->nullable();

            /* ================= REMARKS & FILES ================= */
            $table->string('remarks')->nullable();
            $table->string('attachment')->nullable(); // optional, for uploaded image/file

            $table->timestamps();
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
