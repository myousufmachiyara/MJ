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

            // Product handling
            $table->unsignedBigInteger('item_id')->nullable(); // existing product
            $table->string('temp_product_name')->nullable();   // non-existing product
            $table->enum('item_type', ['simple', 'composite'])->default('simple');

            // Optional variation
            $table->unsignedBigInteger('variation_id')->nullable();

            // Quantities
            $table->decimal('quantity', 15, 2)->default(0);
            $table->unsignedBigInteger('unit');

            // Pricing
            $table->decimal('rate', 15, 2)->default(0);

            $table->string('remarks')->nullable();
            $table->timestamps();

            // FKs
            $table->foreign('purchase_invoice_id')->references('id')->on('purchase_invoices')->onDelete('cascade');
            $table->foreign('item_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('variation_id')->references('id')->on('product_variations')->onDelete('cascade');
            $table->foreign('unit')->references('id')->on('measurement_units')->onDelete('cascade');
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
