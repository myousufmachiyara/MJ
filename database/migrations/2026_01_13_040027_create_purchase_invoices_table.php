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
            $table->foreignId('vendor_id')->constrained('chart_of_accounts');
            $table->date('invoice_date');
            $table->text('remarks')->nullable();

            /* ================= CURRENCY ================= */
            $table->enum('currency', ['AED','USD'])->default('AED'); // AED | USD
            $table->decimal('exchange_rate', 15, 6)->nullable(); // USD → AED
            $table->decimal('net_amount', 18, 2);          // original currency
            $table->decimal('net_amount_aed', 18, 2);      // always stored in AED

            /* ================= PAYMENT ================= */
            $table->enum('payment_method', ['cash','credit','cheque','material+making cost'])->nullable();

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

            /* ================= METAL RATES ================= */
            $table->decimal('gold_rate', 18, 2)->nullable();
            $table->decimal('silver_rate', 18, 2)->nullable();
            $table->decimal('other_metal_rate', 18, 2)->nullable(); // optional

            /* ================= META ================= */
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
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
