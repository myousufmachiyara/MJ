<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SaleInvoice extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'invoice_no',
        'is_taxable',
        'customer_id',
        'invoice_date',
        'remarks',
        'currency',
        'exchange_rate',
        'net_amount',
        'net_amount_aed',
        'payment_method',
        'payment_term',
        // Cheque
        'cheque_no',
        'cheque_date',
        'bank_name',
        'cheque_amount',
        // Bank transfer
        'transfer_from_bank',
        'transfer_to_bank',
        'account_title',
        'account_no',
        'transaction_id',
        'transfer_date',
        'transfer_amount',
        // Material + making
        'material_weight',
        'material_purity',
        'material_value',
        'making_charges',
        'material_received_by',
        'material_given_by',
        // Rates
        'gold_rate_usd',
        'gold_rate_aed_ounce',
        'gold_rate_aed',
        'diamond_rate_usd',
        'diamond_rate_aed',
        'purchase_gold_rate_aed',
        'purchase_making_rate_aed',
        // Meta
        'created_by',
        'received_by',
    ];

    protected $casts = [
        'invoice_date'  => 'date',
        'cheque_date'   => 'date',
        'transfer_date' => 'date',
        'is_taxable'    => 'boolean',

        // Use float (not decimal:N) so arithmetic works correctly.
        // decimal:N returns a string in Laravel, which breaks sum() and comparisons.
        'net_amount'               => 'float',
        'net_amount_aed'           => 'float',
        'exchange_rate'            => 'float',
        'cheque_amount'            => 'float',
        'transfer_amount'          => 'float',
        'material_weight'          => 'float',
        'material_purity'          => 'float',
        'material_value'           => 'float',
        'making_charges'           => 'float',
        'gold_rate_usd'            => 'float',
        'gold_rate_aed_ounce'      => 'float',
        'gold_rate_aed'            => 'float',
        'diamond_rate_usd'         => 'float',
        'diamond_rate_aed'         => 'float',
        'purchase_gold_rate_aed'   => 'float',
        'purchase_making_rate_aed' => 'float',
    ];

    // ── Relationships ──────────────────────────────────────────────────────

    public function customer()
    {
        return $this->belongsTo(ChartOfAccounts::class, 'customer_id');
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
        return $this->hasMany(SaleInvoiceAttachment::class);
    }

    // morphMany is the correct way — matches how PurchaseInvoice uses vouchers()
    public function vouchers()
    {
        return $this->morphMany(Voucher::class, 'reference');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}