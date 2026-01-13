<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseInvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_invoice_id',
        'item_name',
        'product_id',
        'variation_id',
        'item_description',
        'gross_weight',
        'purity',
        'purity_weight',
        'making_rate',
        'making_value',
        'material_value',
        'metal_value',
        'taxable_amount',
        'vat_percent',
        'vat_amount',
        'item_total',
        'metal_type',
        'gold_rate',
        'silver_rate',
        'other_metal_rate',
        'remarks',
        'attachment',
    ];

    /* ================= RELATIONS ================= */
    public function invoice()
    {
        return $this->belongsTo(PurchaseInvoice::class, 'purchase_invoice_id');
    }

    public function product()
    {
        return $this->belongsTo(\App\Models\Product::class, 'product_id');
    }

    public function parts()
    {
        return $this->hasMany(PurchaseInvoiceItemPart::class);
    }
}
