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
        Schema::create('market_rates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');   // usually raw material
            $table->unsignedBigInteger('variation_id'); // shape/size variation
            $table->decimal('rate_per_unit', 18, 2);
            $table->date('effective_date')->useCurrent();
            $table->timestamps();

            // Foreign keys
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('variation_id')->references('id')->on('product_variations')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('market_rates');
    }
};
