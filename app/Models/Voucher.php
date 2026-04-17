<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Voucher extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'voucher_no',
        'voucher_type',
        'voucher_date',
        'reference_type',
        'reference_id',
        'ac_dr_sid',
        'ac_cr_sid',
        'amount',
        'remarks',
        'attachments',
        'created_by',
    ];

    protected $casts = [
        'attachments'  => 'array',
        'voucher_date' => 'date',
        'amount'       => 'float',
    ];

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    /**
     * Source document (PurchaseInvoice, SaleInvoice, etc.)
     */
    public function reference()
    {
        return $this->morphTo();
    }

    /**
     * Multiple accounting entries — used by auto-generated (complex) vouchers.
     */
    public function entries()
    {
        return $this->hasMany(AccountingEntry::class);
    }

    /**
     * Debit COA account — used by simple (manual) vouchers.
     */
    public function debitAccount()
    {
        return $this->belongsTo(ChartOfAccounts::class, 'ac_dr_sid');
    }

    /**
     * Credit COA account — used by simple (manual) vouchers.
     */
    public function creditAccount()
    {
        return $this->belongsTo(ChartOfAccounts::class, 'ac_cr_sid');
    }

    /**
     * User who created the voucher.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeType($query, $type)
    {
        return $query->where('voucher_type', $type);
    }

    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('voucher_date', [$startDate, $endDate]);
    }

    public function scopeForReference($query, $referenceType)
    {
        return $query->where('reference_type', $referenceType);
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Simple voucher = single Dr/Cr pair (payment, receipt, journal).
     * Complex voucher = multiple entries (auto-generated from invoices).
     */
    public function isSimple(): bool
    {
        return !is_null($this->ac_dr_sid)
            && !is_null($this->ac_cr_sid)
            && !is_null($this->amount);
    }

    public function isComplex(): bool
    {
        return !$this->isSimple();
    }

    public function getTotalDebitAttribute(): float
    {
        return $this->isSimple()
            ? (float) $this->amount
            : (float) $this->entries()->sum('debit');
    }

    public function getTotalCreditAttribute(): float
    {
        return $this->isSimple()
            ? (float) $this->amount
            : (float) $this->entries()->sum('credit');
    }

    public function isBalanced(): bool
    {
        return abs($this->total_debit - $this->total_credit) < 0.01;
    }

    /**
     * Generate the next sequential voucher number for a given type.
     *
     * Prefixes:
     *   purchase → PV    sale    → SV
     *   payment  → PAY   receipt → RV
     *   journal  → JV
     */
    public static function generateVoucherNo(string $type): string
    {
        $prefixes = [
            'purchase' => 'PV',
            'sale'     => 'SV',
            'payment'  => 'PAY',
            'receipt'  => 'RV',
            'journal'  => 'JV',
        ];

        $prefix = $prefixes[$type] ?? 'VO';

        $last = self::withTrashed()
            ->where('voucher_no', 'LIKE', $prefix . '-%')
            ->orderBy('id', 'desc')
            ->first();

        $next = $last
            ? (int) str_replace($prefix . '-', '', $last->voucher_no) + 1
            : 1;

        return $prefix . '-' . str_pad($next, 5, '0', STR_PAD_LEFT);
    }
}