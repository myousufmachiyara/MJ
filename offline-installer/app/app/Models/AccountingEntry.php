<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountingEntry extends Model
{
    protected $fillable = [
        'voucher_id',
        'account_id',
        'debit',
        'credit',
        'narration',
    ];

    protected $casts = [
        // Use float so arithmetic works correctly in PHP without string issues.
        // 'decimal:2' returns a string in Laravel which breaks sum() and comparisons.
        'debit'  => 'float',
        'credit' => 'float',
    ];

    // ── Relationships ──────────────────────────────────────────────────────

    public function voucher()
    {
        return $this->belongsTo(Voucher::class);
    }

    public function account()
    {
        return $this->belongsTo(ChartOfAccounts::class, 'account_id');
    }
}