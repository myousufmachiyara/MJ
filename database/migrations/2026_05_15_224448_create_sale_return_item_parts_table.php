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
        Schema::create('sale_return_item_parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_return_item_id')->constrained('sale_return_items')->cascadeOnDelete();
            $table->string('item_name')->nullable();
            $table->string('part_description')->nullable();
            $table->decimal('qty',        15, 4)->default(0);
            $table->decimal('rate',       15, 4)->default(0);
            $table->decimal('stone_qty',  15, 4)->default(0);
            $table->decimal('stone_rate', 15, 4)->default(0);
            $table->decimal('total',      15, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_return_item_parts');
    }
};
