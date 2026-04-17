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

    protected $casts = [
        // Use float so arithmetic and sum() work correctly.
        'qty'        => 'float',
        'rate'       => 'float',
        'stone_qty'  => 'float',
        'stone_rate' => 'float',
        'total'      => 'float',
    ];

    // ── Relationships ──────────────────────────────────────────────────────

    public function saleInvoiceItem()
    {
        return $this->belongsTo(SaleInvoiceItem::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}