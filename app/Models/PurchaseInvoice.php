<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseInvoice extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'invoice_no', 'is_taxable', 'vendor_id', 'invoice_date', 'remarks',
        'currency', 'exchange_rate', 'net_amount', 'net_amount_aed',
        'payment_method', 'payment_term', 'received_by',

        // Gold rates
        'gold_rate_usd',          // USD / oz  (user input)
        'gold_rate_aed_ounce',    // AED / oz  (display; derived from USD × exRate)
        'gold_rate_aed',          // AED / gram ← used in all calculations (÷ 31.1035)

        // Diamond rates
        'diamond_rate_usd',       // USD / Ct  (user input)
        'diamond_rate_aed',       // AED / Ct  ← used in all calculations (direct, no conversion)

        // Cheque
        'cheque_no', 'cheque_date', 'bank_name', 'cheque_amount',

        // Bank transfer
        'transfer_from_bank', 'transfer_to_bank', 'account_title',
        'account_no', 'transaction_id', 'transfer_date', 'transfer_amount',

        // Material + making cost method
        'material_weight', 'material_purity', 'material_value',
        'material_given_by', 'material_received_by', 'making_charges',

        'created_by',
    ];

    protected $casts = [
        'is_taxable'           => 'boolean',
        'invoice_date'         => 'date',
        'cheque_date'          => 'date',
        'transfer_date'        => 'date',
        'net_amount'           => 'decimal:2',
        'net_amount_aed'       => 'decimal:2',
        'exchange_rate'        => 'decimal:6',

        // Gold
        'gold_rate_usd'        => 'decimal:2',
        'gold_rate_aed_ounce'  => 'decimal:2',
        'gold_rate_aed'        => 'decimal:4',   // AED/gram — needs 4dp precision

        // Diamond
        'diamond_rate_usd'       => 'decimal:2',
        'diamond_rate_aed'       => 'decimal:4', // AED/Ct — 4dp precision

        // Payments
        'cheque_amount'        => 'decimal:2',
        'transfer_amount'      => 'decimal:2',
        'material_weight'      => 'decimal:3',
        'material_purity'      => 'decimal:3',
        'material_value'       => 'decimal:2',
        'making_charges'       => 'decimal:2',
    ];

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    public function vendor()
    {
        return $this->belongsTo(ChartOfAccounts::class, 'vendor_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Bank used for cheque payment */
    public function bank()
    {
        return $this->belongsTo(ChartOfAccounts::class, 'bank_name');
    }

    /** Bank used for bank transfer payment */
    public function transferBank()
    {
        return $this->belongsTo(ChartOfAccounts::class, 'transfer_from_bank');
    }

    public function items()
    {
        return $this->hasMany(PurchaseInvoiceItem::class);
    }

    public function attachments()
    {
        return $this->hasMany(PurchaseInvoiceAttachment::class);
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
        )->where('vouchers.reference_type', self::class);
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeForVendor($query, $vendorId)
    {
        return $query->where('vendor_id', $vendorId);
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

    // =========================================================================
    // COMPUTED ATTRIBUTES
    // =========================================================================

    /** Sum of material_value across all items */
    public function getTotalMaterialAttribute()
    {
        return $this->items->sum('material_value');
    }

    /** Sum of making_value across all items */
    public function getTotalMakingAttribute()
    {
        return $this->items->sum('making_value');
    }

    /** Sum of vat_amount across all items */
    public function getTotalVatAttribute()
    {
        return $this->items->sum('vat_amount');
    }

    /**
     * Sum of all part totals across all items.
     * Parts = (diamond CTS × rate) + (stone qty × stone rate)
     */
    public function getTotalPartsAttribute()
    {
        return $this->items->sum(fn($item) => $item->parts->sum('total'));
    }

    /**
     * Diamond part value only: Σ (part qty × part rate)
     */
    public function getTotalDiamondPartsValueAttribute()
    {
        return $this->items->sum(fn($item) => $item->parts->sum(fn($p) => $p->qty * $p->rate));
    }

    /**
     * Stone part value only: Σ (stone qty × stone rate)
     */
    public function getTotalStonePartsValueAttribute()
    {
        return $this->items->sum(fn($item) => $item->parts->sum(fn($p) => ($p->stone_qty ?? 0) * ($p->stone_rate ?? 0)));
    }

    /** Gold material value only */
    public function getGoldMaterialValueAttribute()
    {
        return $this->items->where('material_type', 'gold')->sum('material_value');
    }

    /** Diamond material value only */
    public function getDiamondMaterialValueAttribute()
    {
        return $this->items->where('material_type', 'diamond')->sum('material_value');
    }

    /** Total purity weight across all items */
    public function getTotalPurityWeightAttribute()
    {
        return $this->items->sum('purity_weight');
    }

    /** Total gold gross weight (net_wt + CTS/5) for gold items only */
    public function getTotalGoldGrossWeightAttribute()
    {
        return $this->items->where('material_type', 'gold')->sum('gross_weight');
    }

    /** Total Net Wt (user-entered) across all items */
    public function getTotalNetWeightAttribute()
    {
        return $this->items->sum('net_weight');
    }

    // =========================================================================
    // STATUS HELPERS
    // =========================================================================

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
            'credit'            => 'warning',
            'cash'              => 'success',
            'cheque'            => 'info',
            'bank_transfer'     => 'primary',
            default             => 'secondary',
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

    /**
     * Outstanding amount for credit purchases.
     * Checks payment vouchers made against this invoice.
     */
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

    // =========================================================================
    // STATIC HELPERS
    // =========================================================================

    public static function getTotalForVendor($vendorId, $startDate = null, $endDate = null): float
    {
        $query = self::where('vendor_id', $vendorId);

        if ($startDate && $endDate) {
            $query->whereBetween('invoice_date', [$startDate, $endDate]);
        }

        return (float) $query->sum('net_amount_aed');
    }

    public static function getTotalForDateRange($startDate, $endDate): float
    {
        return (float) self::whereBetween('invoice_date', [$startDate, $endDate])
            ->sum('net_amount_aed');
    }

    public static function getPendingPayments()
    {
        return self::credit()
            ->with('vendor')
            ->get()
            ->filter(fn($invoice) => !$invoice->isFullyPaid());
    }

    // =========================================================================
    // BOOT
    // =========================================================================

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($invoice) {
            $invoice->vouchers()->each(function ($voucher) {
                $voucher->entries()->delete();
                $voucher->delete();
            });

            $invoice->items()->each(function ($item) {
                $item->parts()->delete();
            });
            $invoice->items()->delete();

            $invoice->attachments()->delete();
        });
    }
}