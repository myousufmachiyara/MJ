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
            $table->unsignedBigInteger('vendor_id');
            $table->date('invoice_date');
            $table->text('remarks')->nullable();

            /* ================= CURRENCY ================= */
            $table->enum('currency', ['AED', 'USD'])->default('AED');
            $table->decimal('exchange_rate', 15, 6)->nullable(); // e.g., 3.6725
            $table->decimal('net_amount', 18, 2);               // original currency amount
            $table->decimal('net_amount_aed', 18, 2)->default(0);

            /* ================= PAYMENT ================= */
            $table->enum('payment_method', ['cash', 'credit', 'cheque', 'material+making cost'])->nullable();
            $table->string('payment_term')->nullable(); // Matches your $fillable 'payment_term'

            /* ================= CHEQUE ================= */
            $table->string('cheque_no', 50)->nullable();
            $table->date('cheque_date')->nullable();
            $table->string('bank_name', 100)->nullable();
            $table->decimal('cheque_amount', 18, 2)->nullable();

            /* ================= MATERIAL + MAKING ================= */
            $table->decimal('material_weight', 15, 3)->nullable();
            $table->decimal('material_purity', 10, 3)->nullable();
            $table->decimal('material_value', 18, 2)->nullable();
            $table->decimal('making_charges', 18, 2)->nullable();

            /* ================= METAL RATES (FIXED) ================= */
            // Gold Rates
            $table->decimal('gold_rate_aed', 18, 4)->nullable();
            $table->decimal('gold_rate_usd', 18, 4)->nullable();
            
            // Other Metal / Silver Rates (renamed to match controller 'metal_rate' logic)
            $table->decimal('metal_rate_aed', 18, 4)->nullable();
            $table->decimal('metal_rate_usd', 18, 4)->nullable();

            /* ================= META ================= */
            $table->unsignedBigInteger('created_by');
            $table->string('received_by')->nullable(); // Added to match your model
            $table->timestamps();
            $table->softDeletes();

            /* ================= FOREIGN KEYS ================= */
            $table->foreign('vendor_id')->references('id')->on('chart_of_accounts')->onDelete('cascade');
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
