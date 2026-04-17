<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleInvoiceItemPart extends Model
{
    protected $fillable = [
        'sale_invoice_item_id',
        'product_id',
        'item_name',
        'variation_id',
        'part_description',
        'qty',
        'rate',
        'stone_qty',
        'stone_rate',
        'total',
    ];

    public function saleInvoiceItem()
    {
        return $this->belongsTo(SaleInvoiceItem::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}