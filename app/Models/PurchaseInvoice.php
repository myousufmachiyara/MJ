<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseInvoice extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'invoice_no',
        'vendor_id',
        'invoice_date',
        'remarks',

        'currency',
        'exchange_rate',
        'net_amount',
        'net_amount_aed',

        'payment_method',
        'payment_term',
        'received_by',

        'cheque_no',
        'cheque_date',
        'bank_name',
        'cheque_amount',

        'material_weight',
        'material_purity',
        'material_value',
        'material_given_by',
        'material_received_by',
        'making_charges',

        // header level rates
        'gold_rate_aed',
        'gold_rate_usd',
        'diamond_rate_aed',
        'diamond_rate_usd',


        'created_by',
    ];

    /* ================= RELATIONS ================= */
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

    public function items()
    {
        return $this->hasMany(PurchaseInvoiceItem::class);
    }

    public function attachments()
    {
        return $this->hasMany(PurchaseInvoiceAttachment::class);
    }
}