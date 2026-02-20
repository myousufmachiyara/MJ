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
        Schema::create('sale_invoice_item_parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_invoice_item_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->nullable();
            $table->string('item_name')->nullable();
            $table->foreignId('variation_id')->nullable();
            $table->decimal('qty', 15, 3)->default(0);
            $table->decimal('rate', 15, 2)->default(0);
            $table->decimal('stone_qty', 15, 3)->default(0);
            $table->decimal('stone_rate', 15, 3)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->string('part_description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_invoice_item_parts');
    }
};
