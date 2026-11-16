<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseReturn extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'return_no', 'purchase_invoice_id', 'vendor_id', 'return_date',
        'reason', 'remarks', 'currency', 'exchange_rate',
        'total_material_value', 'total_making_value', 'total_parts_value',
        'total_vat_amount', 'net_amount', 'net_amount_aed',
        'gold_rate_usd', 'gold_rate_aed_ounce', 'gold_rate_aed',
        'diamond_rate_usd', 'diamond_rate_aed',
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
        'net_amount'           => 'decimal:2',
        'net_amount_aed'       => 'decimal:2',
        'total_material_value' => 'decimal:2',
        'total_making_value'   => 'decimal:2',
        'total_parts_value'    => 'decimal:2',
        'total_vat_amount'     => 'decimal:2',
        'exchange_rate'        => 'decimal:6',
        'gold_rate_usd'        => 'decimal:2',
        'gold_rate_aed_ounce'  => 'decimal:2',
        'gold_rate_aed'        => 'decimal:4',
        'diamond_rate_usd'     => 'decimal:2',
        'diamond_rate_aed'     => 'decimal:4',
        'cheque_amount'        => 'decimal:2',
        'transfer_amount'      => 'decimal:2',
    ];

    public function purchaseInvoice()
    {
        return $this->belongsTo(PurchaseInvoice::class);
    }

    public function vendor()
    {
        return $this->belongsTo(ChartOfAccounts::class, 'vendor_id');
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
        return $this->hasMany(PurchaseReturnItem::class);
    }

    public function vouchers()
    {
        return $this->morphMany(Voucher::class, 'reference');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($return) {
            $return->vouchers()->each(function ($voucher) {
                $voucher->entries()->delete();
                $voucher->delete();
            });
            $return->items()->each(function ($item) {
                $item->parts()->delete();
            });
            $return->items()->delete();
        });
    }
}