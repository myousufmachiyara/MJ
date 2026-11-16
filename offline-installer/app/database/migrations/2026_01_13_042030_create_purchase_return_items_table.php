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
        Schema::create('purchase_return_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchase_return_id');
            $table->unsignedBigInteger('purchase_invoice_item_id')->nullable(); // original item reference

            $table->string('item_name')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('item_description')->nullable();

            $table->decimal('net_weight',     15, 4)->default(0);
            $table->decimal('gross_weight',   15, 4)->default(0);
            $table->decimal('purity',         10, 4)->default(0);
            $table->decimal('purity_weight',  15, 4)->default(0);
            $table->decimal('col_995',        15, 4)->default(0);
            $table->string('material_type')->default('gold');
            $table->decimal('material_rate',  18, 4)->default(0);
            $table->decimal('material_value', 18, 4)->default(0);
            $table->decimal('making_rate',    15, 4)->default(0);
            $table->decimal('making_value',   18, 4)->default(0);
            $table->decimal('parts_total',    15, 4)->default(0);
            $table->decimal('taxable_amount', 18, 4)->default(0);
            $table->decimal('vat_percent',     5, 4)->default(0);
            $table->decimal('vat_amount',     18, 4)->default(0);
            $table->decimal('item_total',     18, 4)->default(0);

            $table->string('barcode_number')->nullable()->index();
            $table->timestamps();

            $table->foreign('purchase_return_id')->references('id')->on('purchase_returns')->onDelete('cascade');
            $table->foreign('purchase_invoice_item_id')->references('id')->on('purchase_invoice_items')->onDelete('set null');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_return_items');
    }
};
