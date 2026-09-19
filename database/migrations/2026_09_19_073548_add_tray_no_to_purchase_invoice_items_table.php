<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds tray_no to purchase_invoice_items so each purchased item can
     * record which physical tray it is stored in. Positioned right after
     * subcategory_id to match where the field appears in the Purchase
     * Invoice item row (create/edit forms), just after the Subcategory
     * dropdown.
     */
    public function up(): void
    {
        Schema::table('purchase_invoice_items', function (Blueprint $table) {
            $table->string('tray_no')->nullable()->after('subcategory_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_invoice_items', function (Blueprint $table) {
            $table->dropColumn('tray_no');
        });
    }
};