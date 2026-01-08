<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseInvoiceItemPart extends Model
{
    protected $table = 'purchase_invoice_item_parts';

    protected $fillable = [
        'purchase_invoice_item_id',
        'part_product_id',
        'variation_id',
        'qty',
        'wastage_qty',
        'rate',
    ];

    /* =======================
       Relationships
    ======================= */

    // Parent Item (composite item)
    public function item()
    {
        return $this->belongsTo(PurchaseInvoiceItem::class,'purchase_invoice_item_id');
    }

    // Raw material / Part product
    public function product()
    {
        return $this->belongsTo(Product::class,'part_product_id');
    }

    public function variation()
    {
        return $this->belongsTo(ProductVariation::class, 'variation_id');
    }

    /* =======================
       Accessors (Derived Data)
    ======================= */

    // Total cost of this part
    public function getTotalCostAttribute()
    {
        return ($this->qty + $this->wastage_qty) * $this->rate;
    }
}
