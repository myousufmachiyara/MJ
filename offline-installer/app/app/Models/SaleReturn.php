<?php
// app/Models/SaleReturn.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SaleReturn extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'return_no', 'sale_invoice_id', 'customer_id', 'return_date',
        'reason', 'remarks', 'currency', 'exchange_rate',
        'gold_rate_usd', 'gold_rate_aed_ounce', 'gold_rate_aed',
        'diamond_rate_usd', 'diamond_rate_aed',
        'total_material_value', 'total_making_value', 'total_parts_value',
        'total_vat_amount', 'net_amount', 'net_amount_aed',
        'refund_method',
        'bank_name', 'cheque_no', 'cheque_date', 'cheque_amount',
        'transfer_from_bank', 'transfer_to_bank', 'account_title',
        'account_no', 'transaction_id', 'transfer_date', 'transfer_amount',
        'created_by',
    ];

    protected $casts = [
        'return_date'          => 'date',
        'cheque_date'          => 'date',
        'transfer_date'        => 'date',
        'exchange_rate'        => 'float',
        'gold_rate_usd'        => 'float',
        'gold_rate_aed_ounce'  => 'float',
        'gold_rate_aed'        => 'float',
        'diamond_rate_usd'     => 'float',
        'diamond_rate_aed'     => 'float',
        'total_material_value' => 'float',
        'total_making_value'   => 'float',
        'total_parts_value'    => 'float',
        'total_vat_amount'     => 'float',
        'net_amount'           => 'float',
        'net_amount_aed'       => 'float',
        'cheque_amount'        => 'float',
        'transfer_amount'      => 'float',
    ];

    public function customer()    { return $this->belongsTo(ChartOfAccounts::class, 'customer_id'); }
    public function saleInvoice() { return $this->belongsTo(SaleInvoice::class); }
    public function items()       { return $this->hasMany(SaleReturnItem::class); }
    public function bank()        { return $this->belongsTo(ChartOfAccounts::class, 'bank_name'); }
    public function transferBank(){ return $this->belongsTo(ChartOfAccounts::class, 'transfer_from_bank'); }
}