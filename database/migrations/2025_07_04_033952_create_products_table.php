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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('category_id');
            $table->unsignedBigInteger('subcategory_id')->nullable();
            $table->string('name');
            $table->string('sku')->unique();
            $table->string('barcode')->nullable();
            $table->text('description')->nullable();

            // Inventory & Pricing
            $table->decimal('manufacturing_cost', 10, 2)->default(0);
            $table->decimal('opening_stock', 10, 2)->default(0);
            $table->decimal('selling_price', 10, 2)->default(0);
            $table->decimal('consumption', 10, 2)->default(0);

            // Stock control
            $table->decimal('reorder_level', 10, 2)->default(0);
            $table->decimal('max_stock_level', 10, 2)->default(0);
            $table->decimal('minimum_order_qty', 10, 2)->default(0);

            // Classification
            $table->unsignedBigInteger('measurement_unit');
            $table->string('item_type', 10)->nullable(); // fg, raw, service
            $table->boolean('is_active')->default(true);

            $table->softDeletes();
            $table->timestamps();

            $table->foreign('measurement_unit')->references('id')->on('measurement_units')->onDelete('cascade');
            $table->foreign('category_id')->references('id')->on('product_categories')->onDelete('cascade');
            $table->foreign('subcategory_id')->references('id')->on('product_categories')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
