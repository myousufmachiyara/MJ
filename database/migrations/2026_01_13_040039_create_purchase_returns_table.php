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
        Schema::create('purchase_returns', function (Blueprint $table) {
            $table->id();

            $table->string('return_no', 30)->unique();
            $table->unsignedBigInteger('purchase_invoice_id');
            $table->unsignedBigInteger('vendor_id');
            $table->date('return_date');
            $table->text('reason')->nullable();
            $table->text('remarks')->nullable();

            $table->enum('currency', ['AED', 'USD'])->default('AED');
            $table->decimal('exchange_rate', 15, 6)->nullable();

            // Totals
            $table->decimal('total_material_value', 18, 2)->default(0);
            $table->decimal('total_making_value',   18, 2)->default(0);
            $table->decimal('total_parts_value',    18, 2)->default(0);
            $table->decimal('total_vat_amount',     18, 2)->default(0);
            $table->decimal('net_amount',           18, 2)->default(0);
            $table->decimal('net_amount_aed',       18, 2)->default(0);

            // Gold rates (copied from original invoice)
            $table->decimal('gold_rate_usd',       18, 4)->nullable();
            $table->decimal('gold_rate_aed_ounce', 18, 4)->nullable();
            $table->decimal('gold_rate_aed',       18, 4)->nullable();
            $table->decimal('diamond_rate_usd',    18, 4)->nullable();
            $table->decimal('diamond_rate_aed',    18, 4)->nullable();

            // Refund method
            $table->enum('refund_method', ['credit_note', 'cash', 'bank_transfer', 'cheque', 'material_return'])->nullable();

            // Cheque
            $table->unsignedBigInteger('bank_name')->nullable();
            $table->string('cheque_no', 50)->nullable();
            $table->date('cheque_date')->nullable();
            $table->decimal('cheque_amount', 18, 2)->nullable();

            // Bank transfer
            $table->unsignedBigInteger('transfer_from_bank')->nullable();
            $table->string('transfer_to_bank', 150)->nullable();
            $table->string('account_title', 150)->nullable();
            $table->string('account_no', 100)->nullable();
            $table->string('transaction_id', 100)->nullable();
            $table->date('transfer_date')->nullable();
            $table->decimal('transfer_amount', 18, 2)->nullable();

            $table->unsignedBigInteger('created_by');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('purchase_invoice_id')->references('id')->on('purchase_invoices')->onDelete('cascade');
            $table->foreign('vendor_id')->references('id')->on('chart_of_accounts')->onDelete('cascade');
            $table->foreign('bank_name')->references('id')->on('chart_of_accounts')->onDelete('cascade');
            $table->foreign('transfer_from_bank')->references('id')->on('chart_of_accounts')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_returns');
    }
};
