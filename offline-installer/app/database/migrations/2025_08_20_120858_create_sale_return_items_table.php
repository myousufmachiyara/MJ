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
        Schema::create('sale_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_return_id')->constrained('sale_returns')->cascadeOnDelete();
            $table->unsignedBigInteger('sale_invoice_item_id')->nullable();
            $table->foreign('sale_invoice_item_id')->references('id')->on('sale_invoice_items')->nullOnDelete();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('item_name')->nullable();
            $table->string('item_description')->nullable();
            $table->string('barcode_number')->nullable();
            $table->decimal('gross_weight',   15, 4)->default(0);
            $table->decimal('purity',         15, 4)->default(0);
            $table->decimal('purity_weight',  15, 4)->default(0);
            $table->decimal('col_995',        15, 4)->default(0);
            $table->string('material_type')->default('gold');
            $table->decimal('material_rate',  15, 4)->default(0);
            $table->decimal('material_value', 15, 2)->default(0);
            $table->decimal('making_rate',    15, 4)->default(0);
            $table->decimal('making_value',   15, 2)->default(0);
            $table->decimal('parts_total',    15, 2)->default(0);
            $table->decimal('taxable_amount', 15, 2)->default(0);
            $table->decimal('vat_percent',    15, 2)->default(0);
            $table->decimal('vat_amount',     15, 2)->default(0);
            $table->decimal('item_total',     15, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_return_items');
    }
};
