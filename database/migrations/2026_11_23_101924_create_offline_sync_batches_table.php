<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per "Export for Sync" click (offline side) or successful import
 * (live side) — lets both ends show a plain-language sync history, and lets
 * the Import step recognize a batch it's already processed (so re-uploading
 * the same file is a safe no-op instead of a duplicate import).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offline_sync_batches', function (Blueprint $table) {
            $table->id();
            $table->uuid('batch_id')->unique();
            // 'exported' (written on the offline side) or 'imported'
            // (written on the live side after a successful import).
            $table->string('direction', 10);
            $table->json('table_counts')->nullable(); // {"sale_invoices": 3, ...}
            $table->string('file_name')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offline_sync_batches');
    }
};
