<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * BUG FIX: purchase_invoices.payment_method and sale_invoices.payment_method
     * are MySQL ENUM columns that were created with only:
     *   ['cash', 'credit', 'bank_transfer', 'cheque', 'material+making cost']
     *
     * ...but the app's validation (PurchaseInvoiceController::validateInvoice /
     * SaleInvoiceController::validateInvoice) and payment-field logic
     * (clearIrrelevantPaymentFields()) have always accepted a 5th value,
     * 'material' ("Material Only" — no making charges collected). The ENUM
     * was simply never updated to match, so every save with that option
     * crashes MySQL with:
     *   SQLSTATE[01000]: Warning: 1265 Data truncated for column 'payment_method'
     *
     * Doctrine DBAL isn't required here — MySQL ENUMs are altered with a
     * plain MODIFY COLUMN statement listing the full, new set of values.
     */
    public function up(): void
    {
        if (Schema::hasTable('purchase_invoices')) {
            DB::statement("
                ALTER TABLE purchase_invoices
                MODIFY payment_method ENUM('cash', 'credit', 'bank_transfer', 'cheque', 'material+making cost', 'material') NULL
            ");
        }

        if (Schema::hasTable('sale_invoices')) {
            DB::statement("
                ALTER TABLE sale_invoices
                MODIFY payment_method ENUM('cash', 'credit', 'bank_transfer', 'cheque', 'material+making cost', 'material') NULL
            ");
        }
    }

    /**
     * Reverse the migrations.
     *
     * NOTE: rolling back does NOT delete any 'material' rows that were saved
     * while this migration was applied — if any exist, this ALTER will fail
     * (MySQL can't shrink an ENUM out from under values still in use). That's
     * intentional: it's safer to stop and look than to silently corrupt data.
     */
    public function down(): void
    {
        if (Schema::hasTable('purchase_invoices')) {
            DB::statement("
                ALTER TABLE purchase_invoices
                MODIFY payment_method ENUM('cash', 'credit', 'bank_transfer', 'cheque', 'material+making cost') NULL
            ");
        }

        if (Schema::hasTable('sale_invoices')) {
            DB::statement("
                ALTER TABLE sale_invoices
                MODIFY payment_method ENUM('cash', 'credit', 'bank_transfer', 'cheque', 'material+making cost') NULL
            ");
        }
    }
};
