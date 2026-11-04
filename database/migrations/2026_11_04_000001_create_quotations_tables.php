<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quotation module — standalone tables, completely separate from sale_invoices.
 * Nothing here is linked to vouchers, accounting entries or stock.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotations', function (Blueprint $table) {
            $table->id();

            $table->string('quotation_no', 20)->unique();
            $table->boolean('is_taxable')->default(1);
            $table->unsignedBigInteger('customer_id');
            $table->date('quotation_date');
            $table->text('remarks')->nullable();

            // currency & totals
            $table->enum('currency', ['AED', 'USD'])->default('AED');
            $table->decimal('exchange_rate', 15, 6)->nullable();
            $table->decimal('net_amount', 18, 2)->default(0);
            $table->decimal('net_amount_aed', 18, 2)->default(0);
            $table->decimal('vat_percent', 5, 2)->default(0);
            $table->decimal('vat_amount', 18, 2)->default(0);
            $table->decimal('grand_total', 18, 2)->default(0);

            // whole-quotation discount
            $table->string('discount_type', 10)->nullable();
            $table->decimal('discount_value', 18, 4)->default(0);
            $table->decimal('discount_amount', 18, 2)->default(0);

            // metal rates
            $table->decimal('gold_rate_usd', 18, 4)->nullable();
            $table->decimal('gold_rate_aed_ounce', 18, 4)->nullable();
            $table->decimal('gold_rate_aed', 18, 4)->nullable();
            $table->decimal('diamond_rate_usd', 18, 4)->nullable();
            $table->decimal('diamond_rate_aed', 18, 4)->nullable();
            $table->decimal('purchase_gold_rate_aed', 18, 4)->nullable();
            $table->decimal('purchase_making_rate_aed', 18, 4)->nullable();

            $table->unsignedBigInteger('created_by');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('customer_id')->references('id')->on('chart_of_accounts')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('quotation_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('quotation_id');

            $table->string('item_name')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('variation_id')->nullable();
            $table->string('item_description')->nullable();

            $table->decimal('gross_weight', 15, 4)->default(0);
            $table->decimal('purity', 10, 4)->default(0);
            $table->decimal('purity_weight', 15, 4)->default(0);
            $table->decimal('col_995', 15, 4)->default(0);
            $table->decimal('making_rate', 15, 4)->default(0);
            $table->decimal('making_value', 18, 4)->default(0);
            $table->decimal('parts_total', 15, 4)->default(0);
            $table->decimal('material_rate', 18, 4)->default(0);
            $table->string('material_type');
            $table->decimal('material_value', 18, 4)->default(0);
            $table->decimal('taxable_amount', 18, 4)->default(0);
            $table->decimal('vat_percent', 5, 4)->default(0);
            $table->decimal('vat_amount', 18, 4)->default(0);
            $table->decimal('item_total', 18, 4)->default(0);

            // item-wise discount
            $table->string('discount_type', 10)->nullable();
            $table->decimal('discount_value', 18, 4)->default(0);
            $table->decimal('discount_amount', 18, 2)->default(0);

            $table->decimal('gold_rate', 18, 4)->nullable();
            $table->decimal('diamond_rate', 18, 4)->nullable();

            $table->string('remarks')->nullable();
            // reference only (which stock item was pitched) — no is_printed, nothing is reserved or sold
            $table->string('barcode_number')->nullable()->index();
            $table->timestamps();

            $table->foreign('quotation_id')->references('id')->on('quotations')->onDelete('cascade');
            // a quotation is a record: deleting a product must not delete it
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
            $table->foreign('variation_id')->references('id')->on('product_variations')->nullOnDelete();
        });

        Schema::create('quotation_item_parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_item_id')->constrained('quotation_items')->onDelete('cascade');
            $table->foreignId('product_id')->nullable();
            $table->string('item_name')->nullable();
            $table->foreignId('variation_id')->nullable();
            $table->decimal('qty', 15, 4)->default(0);        // diamond CTS
            $table->decimal('rate', 15, 4)->default(0);
            $table->decimal('stone_qty', 15, 4)->default(0);  // stone CTS
            $table->decimal('stone_rate', 15, 4)->default(0);
            $table->decimal('total', 15, 4)->default(0);
            $table->string('part_description')->nullable();
            $table->timestamps();
        });

        Schema::create('quotation_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained('quotations')->onDelete('cascade');
            $table->string('file_path');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_attachments');
        Schema::dropIfExists('quotation_item_parts');
        Schema::dropIfExists('quotation_items');
        Schema::dropIfExists('quotations');
    }
};
