<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A log row for one "Export for Sync" (offline) or one successful import
 * (live). See the migration that creates this table for the full
 * explanation of what it's for.
 */
class OfflineSyncBatch extends Model
{
    protected $fillable = [
        'batch_id', 'direction', 'table_counts', 'file_name', 'created_by',
    ];

    protected $casts = [
        'table_counts' => 'array',
    ];
}
