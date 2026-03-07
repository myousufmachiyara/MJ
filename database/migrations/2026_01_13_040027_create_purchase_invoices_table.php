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
        Schema::create('purchase_invoices', function (Blueprint $table) {
            $table->id();

            /* ================= BASIC ================= */
            $table->string('invoice_no', 20)->unique();
            $table->boolean('is_taxable')->default(1);
            $table->unsignedBigInteger('vendor_id');
            $table->date('invoice_date');
            $table->text('remarks')->nullable();

            /* ================= CURRENCY ================= */
            $table->enum('currency', ['AED', 'USD'])->default('AED');
            $table->decimal('exchange_rate', 15, 6)->nullable();
            $table->decimal('net_amount', 18, 2);
            $table->decimal('net_amount_aed', 18, 2)->default(0);

            /* ================= PAYMENT ================= */
            $table->enum('payment_method', ['cash', 'credit', 'bank_transfer', 'cheque', 'material+making cost'])->nullable();
            $table->string('payment_term')->nullable();

            /* ================= CHEQUE ================= */
            $table->string('cheque_no', 50)->nullable();
            $table->date('cheque_date')->nullable();
            $table->unsignedBigInteger('bank_name')->nullable();
            $table->decimal('cheque_amount', 18, 2)->nullable();

            /* ================= BANK TRANSFER ================= */
            $table->unsignedBigInteger('transfer_from_bank')->nullable();
            $table->string('transfer_to_bank', 150)->nullable();
            $table->string('account_title', 150)->nullable();
            $table->string('account_no', 100)->nullable();
            $table->string('transaction_id', 100)->nullable();
            $table->date('transfer_date')->nullable();
            $table->decimal('transfer_amount', 18, 2)->nullable();

            /* ================= MATERIAL + MAKING ================= */
            $table->decimal('material_weight', 15, 3)->nullable();
            $table->decimal('material_purity', 10, 3)->nullable();
            $table->decimal('material_value', 18, 2)->nullable();
            $table->decimal('making_charges', 18, 2)->nullable();
            $table->string('material_received_by', 100)->nullable();
            $table->string('material_given_by', 100)->nullable();

            /* ================= METAL RATES ================= */
            // Gold — three columns:
            //   gold_rate_usd        : user input (USD/oz)
            //   gold_rate_aed_ounce  : AED/oz — auto-filled (USD × exchange_rate), display only
            //   gold_rate_aed        : AED/gram — used in all calculations (÷ 31.1035)
            $table->decimal('gold_rate_usd', 18, 4)->nullable();
            $table->decimal('gold_rate_aed_ounce', 18, 4)->nullable();
            $table->decimal('gold_rate_aed', 18, 4)->nullable();

            // Diamond — two columns:
            //   diamond_rate_usd : user input (USD/Ct)
            //   diamond_rate_aed : AED/Ct — auto-filled (USD × exchange_rate) or direct entry
            //                      no gram/ounce conversion; used as-is in calculations
            $table->decimal('diamond_rate_usd', 18, 4)->nullable();
            $table->decimal('diamond_rate_aed', 18, 4)->nullable();

            /* ================= META ================= */
            $table->unsignedBigInteger('created_by');
            $table->string('received_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            /* ================= FOREIGN KEYS ================= */
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
        Schema::dropIfExists('purchase_invoices');
    }
};
