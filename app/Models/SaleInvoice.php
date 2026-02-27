<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SaleInvoice extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'invoice_no', 'is_taxable', 'customer_id', 'invoice_date', 'remarks',
        'currency', 'exchange_rate', 'net_amount', 'net_amount_aed',
        'payment_method', 'payment_term', 'received_by',
        'cheque_no', 'cheque_date', 'bank_name', 'cheque_amount',
        'material_weight', 'material_purity', 'material_value',
        'material_given_by', 'material_received_by', 'making_charges',
        'transfer_from_bank', 'transfer_to_bank', 'account_title',
        'account_no', 'transaction_id', 'transfer_date', 'transfer_amount',
        'gold_rate_aed', 'gold_rate_usd', 'diamond_rate_aed', 'diamond_rate_usd',
        'purchase_gold_rate_aed', 'purchase_making_rate_aed',   // <-- profit % fields
        'created_by',
    ];

    protected $casts = [
        'is_taxable'               => 'boolean',
        'invoice_date'             => 'date',
        'cheque_date'              => 'date',
        'transfer_date'            => 'date',
        'net_amount'               => 'decimal:2',
        'net_amount_aed'           => 'decimal:2',
        'exchange_rate'            => 'decimal:4',
        'gold_rate_aed'            => 'decimal:2',
        'gold_rate_usd'            => 'decimal:2',
        'diamond_rate_aed'         => 'decimal:2',
        'diamond_rate_usd'         => 'decimal:2',
        'purchase_gold_rate_aed'   => 'decimal:4',
        'purchase_making_rate_aed' => 'decimal:4',
        'cheque_amount'            => 'decimal:2',
        'transfer_amount'          => 'decimal:2',
        'material_weight'          => 'decimal:3',
        'material_purity'          => 'decimal:3',
        'material_value'           => 'decimal:2',
        'making_charges'           => 'decimal:2',
    ];

    // ========================================
    // RELATIONSHIPS
    // ========================================

    public function customer()
    {
        return $this->belongsTo(ChartOfAccounts::class, 'customer_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function bank()
    {
        return $this->belongsTo(ChartOfAccounts::class, 'bank_name');
    }

    public function transferBank()
    {
        return $this->belongsTo(ChartOfAccounts::class, 'transfer_from_bank');
    }

    public function items()
    {
        return $this->hasMany(SaleInvoiceItem::class);
    }

    public function attachments()
    {
        return $this->hasMany(SaleInvoiceAttachment::class, 'sale_invoice_id');
    }

    public function vouchers()
    {
        return $this->morphMany(Voucher::class, 'reference');
    }

    public function accountingEntries()
    {
        return $this->hasManyThrough(
            AccountingEntry::class,
            Voucher::class,
            'reference_id',
            'voucher_id',
            'id',
            'id'
        )->where('vouchers.reference_type', 'App\Models\SaleInvoice');
    }

    // ========================================
    // SCOPES
    // ========================================

    public function scopeForCustomer($query, $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopePaymentMethod($query, $method)
    {
        return $query->where('payment_method', $method);
    }

    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('invoice_date', [$startDate, $endDate]);
    }

    public function scopeTaxable($query)
    {
        return $query->where('is_taxable', true);
    }

    public function scopeNonTaxable($query)
    {
        return $query->where('is_taxable', false);
    }

    public function scopeCurrency($query, $currency)
    {
        return $query->where('currency', $currency);
    }

    public function scopeCredit($query)
    {
        return $query->where('payment_method', 'credit');
    }

    // ========================================
    // HELPER ATTRIBUTES
    // ========================================

    public function getTotalMaterialAttribute()
    {
        return $this->items->sum('material_value');
    }

    public function getTotalMakingAttribute()
    {
        return $this->items->sum('making_value');
    }

    public function getTotalVatAttribute()
    {
        return $this->items->sum('vat_amount');
    }

    public function getTotalPartsAttribute()
    {
        return $this->items->sum(fn($item) => $item->parts->sum('total'));
    }

    public function getGoldMaterialValueAttribute()
    {
        return $this->items->where('material_type', 'gold')->sum('material_value');
    }

    public function getDiamondMaterialValueAttribute()
    {
        return $this->items->where('material_type', 'diamond')->sum('material_value');
    }

    public function getTotalPurityWeightAttribute()
    {
        return $this->items->sum('purity_weight');
    }

    // ========================================
    // PROFIT % ATTRIBUTES
    // ========================================

    /**
     * Overall invoice profit % based on stored purchase rates.
     * Returns null if no cost rates are set.
     */
    public function getOverallProfitPctAttribute(): ?float
    {
        $purchaseGoldRate   = $this->purchase_gold_rate_aed   ?? 0;
        $purchaseMakingRate = $this->purchase_making_rate_aed  ?? 0;

        if ($purchaseGoldRate == 0 && $purchaseMakingRate == 0) {
            return null;
        }

        $totalCost = $this->items->sum(
            fn($item) => ($purchaseGoldRate * $item->purity_weight)
                       + ($item->gross_weight * $purchaseMakingRate)
        );

        if ($totalCost <= 0) {
            return null;
        }

        return round((($this->net_amount - $totalCost) / $totalCost) * 100, 2);
    }

    /**
     * Overall profit in AED (sale - cost).
     */
    public function getOverallProfitAmountAttribute(): ?float
    {
        $purchaseGoldRate   = $this->purchase_gold_rate_aed   ?? 0;
        $purchaseMakingRate = $this->purchase_making_rate_aed  ?? 0;

        $totalCost = $this->items->sum(
            fn($item) => ($purchaseGoldRate * $item->purity_weight)
                       + ($item->gross_weight * $purchaseMakingRate)
        );

        return round($this->net_amount - $totalCost, 2);
    }

    // ========================================
    // STATUS HELPERS
    // ========================================

    public function hasAccountingEntries(): bool
    {
        return $this->vouchers()->exists();
    }

    public function isPending(): bool
    {
        return $this->payment_method === 'credit';
    }

    public function isPaid(): bool
    {
        return in_array($this->payment_method, ['cash', 'cheque', 'bank_transfer']);
    }

    public function isMaterialMethod(): bool
    {
        return str_contains($this->payment_method, 'material');
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->payment_method) {
            'credit'       => 'warning',
            'cash'         => 'success',
            'cheque'       => 'info',
            'bank_transfer'=> 'primary',
            default        => 'secondary',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->payment_method) {
            'credit'               => 'Pending Payment',
            'cash'                 => 'Paid - Cash',
            'cheque'               => 'Paid - Cheque',
            'bank_transfer'        => 'Paid - Bank Transfer',
            'material+making cost' => 'Material + Making',
            default                => ucfirst($this->payment_method),
        };
    }

    public function getInvoiceTypeAttribute(): string
    {
        return $this->is_taxable ? 'Tax Invoice' : 'Non-Tax Invoice';
    }

    public function getOutstandingAmountAttribute(): float
    {
        if ($this->payment_method !== 'credit') {
            return 0;
        }

        $totalPaid = $this->vouchers()
            ->where('voucher_type', 'payment')
            ->sum('amount');

        return max(0, $this->net_amount_aed - $totalPaid);
    }

    public function isFullyPaid(): bool
    {
        return $this->outstanding_amount <= 0.01;
    }

    // ========================================
    // STATIC HELPERS
    // ========================================

    public static function getTotalForCustomer($customerId, $startDate = null, $endDate = null): float
    {
        $query = self::where('customer_id', $customerId);

        if ($startDate && $endDate) {
            $query->whereBetween('invoice_date', [$startDate, $endDate]);
        }

        return $query->sum('net_amount_aed');
    }

    public static function getTotalForDateRange($startDate, $endDate): float
    {
        return self::whereBetween('invoice_date', [$startDate, $endDate])
            ->sum('net_amount_aed');
    }

    public static function getPendingPayments()
    {
        return self::credit()
            ->with('customer')
            ->get()
            ->filter(fn($invoice) => !$invoice->isFullyPaid());
    }

    // ========================================
    // BOOT
    // ========================================

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($invoice) {
            $invoice->vouchers()->delete();

            $invoice->items()->each(function ($item) {
                $item->parts()->delete();
            });
            $invoice->items()->delete();

            $invoice->attachments()->delete();
        });
    }
}