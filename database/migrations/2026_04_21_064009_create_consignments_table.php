<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // =====================================================================
        // CONSIGNMENTS — header
        //   direction = 'inbound'  → another business gives us their goods to sell
        //   direction = 'outbound' → we give our goods to another business to sell
        // =====================================================================
        Schema::create('consignments', function (Blueprint $table) {
            $table->id();
            $table->string('consignment_no', 30)->unique();

            $table->enum('direction', ['inbound', 'outbound']);

            // The counter-party (customer or vendor in COA)
            $table->unsignedBigInteger('partner_id');

            $table->date('start_date');
            $table->date('end_date')->nullable();          // null = open-ended
            $table->string('duration_label', 60)->nullable(); // "3 months" etc.

            $table->enum('status', [
                'active', 'partially_settled', 'settled', 'returned', 'expired'
            ])->default('active');

            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('created_by');

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('partner_id')->references('id')->on('chart_of_accounts')->onDelete('restrict');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('restrict');

            $table->index(['direction', 'status']);
            $table->index('start_date');
        });

        // =====================================================================
        // CONSIGNMENT_ITEMS — per item inside a consignment
        //   For INBOUND items: barcode_number is generated (CSG-XXXXX-1) so
        //   the sale scanner can scan them exactly like a purchase barcode.
        //   status: in_stock → sold → (settled_by_sale_invoice_id set)
        //           in_stock → returned
        // =====================================================================
        Schema::create('consignment_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('consignment_id');

            // Product / description
            $table->string('item_name')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('item_description')->nullable();

            // Barcode — generated for inbound, null for outbound
            $table->string('barcode_number', 40)->nullable();
            $table->boolean('is_printed')->default(false);

            // Weight / purity (same columns as purchase/sale items)
            $table->decimal('gross_weight',  15, 4)->default(0);
            $table->decimal('purity',        10, 4)->default(0);
            $table->decimal('purity_weight', 15, 4)->default(0);
            $table->decimal('col_995',       15, 4)->default(0);

            // Making
            $table->decimal('making_rate',  15, 4)->default(0);
            $table->decimal('making_value', 18, 4)->default(0);

            // Material
            $table->string('material_type', 20)->default('gold'); // gold | diamond
            $table->decimal('material_rate',  18, 4)->default(0);
            $table->decimal('material_value', 18, 4)->default(0);

            // Parts / stones
            $table->decimal('parts_total', 15, 4)->default(0);

            // VAT
            $table->decimal('taxable_amount', 18, 4)->default(0);
            $table->decimal('vat_percent',     5, 4)->default(0);
            $table->decimal('vat_amount',     18, 4)->default(0);

            // Agreed value at time of consignment
            $table->decimal('agreed_value', 18, 4)->default(0);

            // Item lifecycle
            $table->enum('item_status', ['in_stock', 'sold', 'returned'])->default('in_stock');
            $table->unsignedBigInteger('settled_by_sale_invoice_id')->nullable();
            $table->date('settled_date')->nullable();

            $table->timestamps();

            $table->foreign('consignment_id')->references('id')->on('consignments')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('set null');
            $table->foreign('settled_by_sale_invoice_id')->references('id')->on('sale_invoices')->onDelete('set null');

            $table->index('barcode_number');
            $table->index('item_status');
        });

        // =====================================================================
        // CONSIGNMENT_ITEM_PARTS — diamonds / stones per item (mirrors purchase/sale)
        // =====================================================================
        Schema::create('consignment_item_parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consignment_item_id')->constrained()->onDelete('cascade');

            $table->string('item_name')->nullable();
            $table->string('part_description')->nullable();
            $table->decimal('qty',        15, 4)->default(0); // diamond CTS
            $table->decimal('rate',       15, 4)->default(0);
            $table->decimal('stone_qty',  15, 4)->default(0);
            $table->decimal('stone_rate', 15, 4)->default(0);
            $table->decimal('total',      15, 4)->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consignment_item_parts');
        Schema::dropIfExists('consignment_items');
        Schema::dropIfExists('consignments');
    }
};