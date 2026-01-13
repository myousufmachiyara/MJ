<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseInvoiceItemPart extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_invoice_item_id',
        'product_id',
        'variation_id',
        'qty',
        'rate',
        'wastage',
        'total',
        'metal_weight',
        'metal_rate',
        'metal_value',
        'part_description',
    ];


    /* ================= RELATIONS ================= */
    public function item()
    {
        return $this->belongsTo(PurchaseInvoiceItem::class, 'purchase_invoice_item_id');
    }

    public function product()
    {
        return $this->belongsTo(\App\Models\Product::class, 'product_id');
    }
}
