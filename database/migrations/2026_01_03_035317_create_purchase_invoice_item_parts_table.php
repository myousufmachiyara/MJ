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
        Schema::create('purchase_invoice_item_parts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchase_invoice_item_id');

            // Part (raw material / existing product)
            $table->unsignedBigInteger('part_product_id');
            $table->unsignedBigInteger('variation_id')->nullable();

            $table->decimal('qty', 15, 2);
            $table->decimal('wastage_qty', 15, 2)->default(0);
            $table->decimal('rate', 15, 2);

            $table->timestamps();

            $table->foreign('purchase_invoice_item_id')->references('id')->on('purchase_invoice_items')->onDelete('cascade');
            $table->foreign('part_product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('variation_id')->references('id')->on('product_variations')->onDelete('cascade');

        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_invoice_item_parts');
    }
};
