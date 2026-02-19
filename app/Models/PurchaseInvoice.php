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
        'cheque_no', 'cheque_date', 'bank_name', 'cheque_amount',
        'material_weight', 'material_purity', 'material_value',
        'material_given_by', 'material_received_by', 'making_charges',
        'transfer_from_bank', 'transfer_to_bank', 'account_title', 
        'account_no', 'transaction_id', 'transfer_date', 'transfer_amount',
        'gold_rate_aed', 'gold_rate_usd', 'diamond_rate_aed', 'diamond_rate_usd',
        'created_by',
    ];

    protected $casts = [
        'is_taxable' => 'boolean',
        'invoice_date' => 'date',
        'cheque_date' => 'date',
        'transfer_date' => 'date',
        'net_amount' => 'decimal:2',
        'net_amount_aed' => 'decimal:2',
        'exchange_rate' => 'decimal:4',
        'gold_rate_aed' => 'decimal:2',
        'gold_rate_usd' => 'decimal:2',
        'diamond_rate_aed' => 'decimal:2',
        'diamond_rate_usd' => 'decimal:2',
        'cheque_amount' => 'decimal:2',
        'transfer_amount' => 'decimal:2',
        'material_weight' => 'decimal:3',
        'material_purity' => 'decimal:3',
        'material_value' => 'decimal:2',
        'making_charges' => 'decimal:2',
    ];

    // ========================================
    // RELATIONSHIPS
    // ========================================
    
    public function vendor()
    {
        return $this->belongsTo(ChartOfAccounts::class, 'vendor_id');
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
        )->where('vouchers.reference_type', 'App\Models\PurchaseInvoice');
    }

    // ========================================
    // SCOPES
    // ========================================
    
    /**
     * Filter by vendor
     */
    public function scopeForVendor($query, $vendorId)
    {
        return $query->where('vendor_id', $vendorId);
    }

    /**
     * Filter by payment method
     */
    public function scopePaymentMethod($query, $method)
    {
        return $query->where('payment_method', $method);
    }

    /**
     * Filter by date range
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('invoice_date', [$startDate, $endDate]);
    }

    /**
     * Filter taxable invoices
     */
    public function scopeTaxable($query)
    {
        return $query->where('is_taxable', true);
    }

    /**
     * Filter non-taxable invoices
     */
    public function scopeNonTaxable($query)
    {
        return $query->where('is_taxable', false);
    }

    /**
     * Filter by currency
     */
    public function scopeCurrency($query, $currency)
    {
        return $query->where('currency', $currency);
    }

    /**
     * Get credit purchases (unpaid)
     */
    public function scopeCredit($query)
    {
        return $query->where('payment_method', 'credit');
    }

    // ========================================
    // HELPER METHODS & ATTRIBUTES
    // ========================================
    
    /**
     * Get total material value
     */
    public function getTotalMaterialAttribute()
    {
        return $this->items->sum('material_value');
    }

    /**
     * Get total making charges
     */
    public function getTotalMakingAttribute()
    {
        return $this->items->sum('making_value');
    }

    /**
     * Get total VAT amount
     */
    public function getTotalVatAttribute()
    {
        return $this->items->sum('vat_amount');
    }

    /**
     * Get total parts value
     */
    public function getTotalPartsAttribute()
    {
        return $this->items->sum(function ($item) {
            return $item->parts->sum('total');
        });
    }

    /**
     * Get gold material value only
     */
    public function getGoldMaterialValueAttribute()
    {
        return $this->items->where('material_type', 'gold')->sum('material_value');
    }

    /**
     * Get diamond material value only
     */
    public function getDiamondMaterialValueAttribute()
    {
        return $this->items->where('material_type', 'diamond')->sum('material_value');
    }

    /**
     * Get total purity weight (for metal fixing reports)
     */
    public function getTotalPurityWeightAttribute()
    {
        return $this->items->sum('purity_weight');
    }

    /**
     * Check if invoice has accounting entries
     */
    public function hasAccountingEntries()
    {
        return $this->vouchers()->exists();
    }

    /**
     * Check if payment is pending (credit purchase)
     */
    public function isPending()
    {
        return $this->payment_method === 'credit';
    }

    /**
     * Check if payment is completed
     */
    public function isPaid()
    {
        return in_array($this->payment_method, ['cash', 'cheque', 'bank_transfer']);
    }

    /**
     * Check if invoice uses material + making cost method
     */
    public function isMaterialMethod()
    {
        return str_contains($this->payment_method, 'material');
    }

    /**
     * Get invoice status badge color
     */
    public function getStatusColorAttribute()
    {
        return match($this->payment_method) {
            'credit' => 'warning',
            'cash' => 'success',
            'cheque' => 'info',
            'bank_transfer' => 'primary',
            default => 'secondary',
        };
    }

    /**
     * Get invoice status label
     */
    public function getStatusLabelAttribute()
    {
        return match($this->payment_method) {
            'credit' => 'Pending Payment',
            'cash' => 'Paid - Cash',
            'cheque' => 'Paid - Cheque',
            'bank_transfer' => 'Paid - Bank Transfer',
            'material+making cost' => 'Material + Making',
            default => ucfirst($this->payment_method),
        };
    }

    /**
     * Get formatted invoice number
     */
    public function getFormattedInvoiceNoAttribute()
    {
        return $this->invoice_no;
    }

    /**
     * Get invoice type (Tax or Non-Tax)
     */
    public function getInvoiceTypeAttribute()
    {
        return $this->is_taxable ? 'Tax Invoice' : 'Non-Tax Invoice';
    }

    /**
     * Calculate outstanding amount (for credit purchases)
     */
    public function getOutstandingAmountAttribute()
    {
        if ($this->payment_method !== 'credit') {
            return 0;
        }

        // Get total payments made against this invoice
        $totalPaid = $this->vouchers()
            ->where('voucher_type', 'payment')
            ->sum('amount');

        return max(0, $this->net_amount_aed - $totalPaid);
    }

    /**
     * Check if invoice is fully paid
     */
    public function isFullyPaid()
    {
        return $this->outstanding_amount <= 0.01; // Allow 0.01 rounding difference
    }

    // ========================================
    // STATIC HELPER METHODS
    // ========================================
    
    /**
     * Get total purchases for a vendor
     */
    public static function getTotalForVendor($vendorId, $startDate = null, $endDate = null)
    {
        $query = self::where('vendor_id', $vendorId);
        
        if ($startDate && $endDate) {
            $query->whereBetween('invoice_date', [$startDate, $endDate]);
        }
        
        return $query->sum('net_amount_aed');
    }

    /**
     * Get total purchases for a date range
     */
    public static function getTotalForDateRange($startDate, $endDate)
    {
        return self::whereBetween('invoice_date', [$startDate, $endDate])
            ->sum('net_amount_aed');
    }

    /**
     * Get pending payments (credit purchases)
     */
    public static function getPendingPayments()
    {
        return self::credit()
            ->with('vendor')
            ->get()
            ->filter(fn($invoice) => !$invoice->isFullyPaid());
    }

    /**
     * Boot method for model events
     */
    protected static function boot()
    {
        parent::boot();

        // When deleting invoice, delete related vouchers
        static::deleting(function ($invoice) {
            // Delete vouchers (cascade will delete entries)
            $invoice->vouchers()->delete();
            
            // Delete items and parts
            $invoice->items()->each(function ($item) {
                $item->parts()->delete();
            });
            $invoice->items()->delete();
            
            // Delete attachments
            $invoice->attachments()->delete();
        });
    }
}