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
        Schema::create('purchase_invoices_1', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_no', 10)->unique();
            $table->unsignedBigInteger('vendor_id');
            $table->date('invoice_date');
            $table->text('remarks')->nullable();

            // Payment
            $table->string('payment_method')->nullable();

            // Cheque details
            $table->string('cheque_no')->nullable();
            $table->date('cheque_date')->nullable();
            $table->string('bank_name')->nullable();
            $table->decimal('cheque_amount', 15, 2)->nullable();

            // Material + Making
            $table->decimal('material_weight', 15, 2)->nullable();
            $table->decimal('material_purity', 10, 2)->nullable();
            $table->decimal('material_value', 15, 2)->nullable();
            $table->decimal('making_charges', 15, 2)->nullable();

            $table->decimal('net_amount', 15, 2)->nullable();

            $table->unsignedBigInteger('created_by');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('vendor_id')->references('id')->on('chart_of_accounts');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_invoices_1');
    }
};
