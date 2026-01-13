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
        'cheque_no',
        'cheque_date',
        'bank_name',
        'cheque_amount',
        'material_weight',
        'material_purity',
        'material_value',
        'making_charges',
        'gold_rate',
        'silver_rate',
        'other_metal_rate',
        'created_by',
    ];

    /* ================= RELATIONS ================= */
    public function vendor()
    {
        return $this->belongsTo(\App\Models\ChartOfAccount::class, 'vendor_id');
    }

    public function creator()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
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
