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
        Schema::create('purchase_invoices_1_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchase_invoice_id');

            // Product
            $table->string('item_description')->nullable();

            // Item details
            $table->decimal('purity', 10, 2)->default(0);
            $table->decimal('gross_weight', 15, 2)->default(0);
            $table->decimal('purity_weight', 15, 2)->default(0);
            $table->decimal('making_rate', 15, 2)->default(0);
            $table->decimal('metal_value', 15, 2)->default(0);
            $table->decimal('taxable_amount', 15, 2)->default(0);
            $table->decimal('vat_percent', 15, 2)->default(0);

            $table->string('remarks')->nullable();
            $table->timestamps();

            // FK
            $table->foreign('purchase_invoice_id')->references('id')->on('purchase_invoices_1')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_invoices_1_items');
    }
};
