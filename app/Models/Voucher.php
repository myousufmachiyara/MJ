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
        'attachments' => 'array',
        'voucher_date' => 'date',
    ];

    // ========================================
    // RELATIONSHIPS
    // ========================================
    
    /**
     * Polymorphic relationship to source document
     * (PurchaseInvoice, SaleInvoice, etc.)
     */
    public function reference()
    {
        return $this->morphTo();
    }

    /**
     * Multiple accounting entries for complex vouchers
     */
    public function entries()
    {
        return $this->hasMany(AccountingEntry::class);
    }

    /**
     * Debit account for simple vouchers
     */
    public function debitAccount()
    {
        return $this->belongsTo(ChartOfAccounts::class, 'ac_dr_sid');
    }

    /**
     * Credit account for simple vouchers
     */
    public function creditAccount()
    {
        return $this->belongsTo(ChartOfAccounts::class, 'ac_cr_sid');
    }

    /**
     * User who created the voucher
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Related production (if voucher is for production)
     */
    public function production()
    {
        return $this->hasOne(Production::class, 'voucher_id');
    }

    // ========================================
    // SCOPES
    // ========================================
    
    /**
     * Filter by voucher type
     */
    public function scopeType($query, $type)
    {
        return $query->where('voucher_type', $type);
    }

    /**
     * Filter by date range
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('voucher_date', [$startDate, $endDate]);
    }

    /**
     * Filter by reference (e.g., all vouchers for PurchaseInvoices)
     */
    public function scopeReference($query, $referenceType)
    {
        return $query->where('reference_type', $referenceType);
    }

    // ========================================
    // HELPER METHODS
    // ========================================
    
    /**
     * Check if voucher is simple (single Dr/Cr pair)
     */
    public function isSimple()
    {
        return !is_null($this->ac_dr_sid) && !is_null($this->ac_cr_sid) && !is_null($this->amount);
    }

    /**
     * Check if voucher is complex (multiple entries)
     */
    public function isComplex()
    {
        return $this->entries()->exists();
    }

    /**
     * Get total debit amount (works for both simple and complex)
     */
    public function getTotalDebitAttribute()
    {
        if ($this->isSimple()) {
            return $this->amount;
        }
        return $this->entries()->sum('debit');
    }

    /**
     * Get total credit amount (works for both simple and complex)
     */
    public function getTotalCreditAttribute()
    {
        if ($this->isSimple()) {
            return $this->amount;
        }
        return $this->entries()->sum('credit');
    }

    /**
     * Check if voucher is balanced
     */
    public function isBalanced()
    {
        return abs($this->total_debit - $this->total_credit) < 0.01; // Allow 0.01 rounding difference
    }

    /**
     * Generate next voucher number
     */
    public static function generateVoucherNo($type)
    {
        $prefixes = [
            'purchase' => 'PV',
            'sale' => 'SV',
            'payment' => 'PAY',
            'receipt' => 'RV',
            'journal' => 'JV',
            'purchase_return' => 'PR',
            'sale_return' => 'SR',
        ];

        $prefix = $prefixes[$type] ?? 'VO';
        
        $lastVoucher = self::withTrashed()
            ->where('voucher_no', 'LIKE', $prefix . '-%')
            ->orderBy('id', 'desc')
            ->first();

        $nextNumber = $lastVoucher 
            ? intval(str_replace($prefix . '-', '', $lastVoucher->voucher_no)) + 1 
            : 1;

        return $prefix . '-' . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);
    }
}