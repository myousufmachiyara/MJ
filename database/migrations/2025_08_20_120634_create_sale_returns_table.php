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
        Schema::create('sale_returns', function (Blueprint $table) {
            $table->id();
            $table->string('return_no')->unique();
            $table->foreignId('sale_invoice_id')->constrained('sale_invoices')->cascadeOnDelete();
            $table->unsignedBigInteger('customer_id');
            $table->date('return_date');
            $table->string('reason');
            $table->text('remarks')->nullable();
            $table->string('currency')->default('AED');
            $table->decimal('exchange_rate', 15, 6)->nullable();
            // Rates (copied from original invoice)
            $table->decimal('gold_rate_usd',       15, 6)->nullable();
            $table->decimal('gold_rate_aed_ounce', 15, 6)->nullable();
            $table->decimal('gold_rate_aed',       15, 6)->nullable();
            $table->decimal('diamond_rate_usd',    15, 6)->nullable();
            $table->decimal('diamond_rate_aed',    15, 6)->nullable();
            // Totals
            $table->decimal('total_material_value', 15, 2)->default(0);
            $table->decimal('total_making_value',   15, 2)->default(0);
            $table->decimal('total_parts_value',    15, 2)->default(0);
            $table->decimal('total_vat_amount',     15, 2)->default(0);
            $table->decimal('net_amount',           15, 2)->default(0);
            $table->decimal('net_amount_aed',       15, 2)->default(0);
            // Refund method
            $table->enum('refund_method', ['credit_note', 'cash', 'bank_transfer', 'cheque', 'material_return']);
            // Cheque
            $table->unsignedBigInteger('bank_name')->nullable();
            $table->string('cheque_no')->nullable();
            $table->date('cheque_date')->nullable();
            $table->decimal('cheque_amount', 15, 2)->nullable();
            // Bank transfer
            $table->unsignedBigInteger('transfer_from_bank')->nullable();
            $table->string('transfer_to_bank')->nullable();
            $table->string('account_title')->nullable();
            $table->string('account_no')->nullable();
            $table->string('transaction_id')->nullable();
            $table->date('transfer_date')->nullable();
            $table->decimal('transfer_amount', 15, 2)->nullable();
            // Meta
            $table->unsignedBigInteger('created_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_returns');
    }
};
