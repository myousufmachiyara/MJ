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
        // cheque
        'cheque_no',
        'cheque_date',
        'bank_name',
        'cheque_amount',
        // bank transfer
        'transfer_from_bank',
        'transfer_to_bank',
        'account_title',
        'account_no',
        'transaction_id',
        'transfer_date',
        'transfer_amount',
        // material+making
        'material_weight',
        'material_purity',
        'material_value',
        'making_charges',
        'material_received_by',
        'material_given_by',
        // rates
        'gold_rate_usd',
        'gold_rate_aed_ounce',
        'gold_rate_aed',
        'diamond_rate_usd',
        'diamond_rate_aed',
        'purchase_gold_rate_aed',
        'purchase_making_rate_aed',
        // meta
        'created_by',
        'received_by',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'cheque_date'  => 'date',
        'transfer_date'=> 'date',
        'is_taxable'   => 'boolean',
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

    public function vouchers()
    {
        return $this->hasMany(Voucher::class, 'reference_id')
            ->where('reference_type', self::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}