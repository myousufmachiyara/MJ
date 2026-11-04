<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Customer quotation — record only. No voucher, no ledger, no inventory.
 */
class Quotation extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'quotation_no',
        'is_taxable',
        'customer_id',
        'quotation_date',
        'remarks',
        'currency',
        'exchange_rate',
        'net_amount',
        'net_amount_aed',
        'vat_percent',
        'vat_amount',
        'grand_total',
        'discount_type',
        'discount_value',
        'discount_amount',
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
    ];

    protected $casts = [
        'quotation_date'           => 'date',
        'is_taxable'               => 'boolean',
        'net_amount'               => 'float',
        'net_amount_aed'           => 'float',
        'exchange_rate'            => 'float',
        'vat_percent'              => 'float',
        'vat_amount'               => 'float',
        'grand_total'              => 'float',
        'discount_value'           => 'float',
        'discount_amount'          => 'float',
        'gold_rate_usd'            => 'float',
        'gold_rate_aed_ounce'      => 'float',
        'gold_rate_aed'            => 'float',
        'diamond_rate_usd'         => 'float',
        'diamond_rate_aed'         => 'float',
        'purchase_gold_rate_aed'   => 'float',
        'purchase_making_rate_aed' => 'float',
    ];

    public function customer()
    {
        return $this->belongsTo(ChartOfAccounts::class, 'customer_id');
    }

    public function items()
    {
        return $this->hasMany(QuotationItem::class);
    }

    public function attachments()
    {
        return $this->hasMany(QuotationAttachment::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
