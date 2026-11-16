<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuotationItemPart extends Model
{
    protected $fillable = [
        'quotation_item_id',
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
        'qty'        => 'float',
        'rate'       => 'float',
        'stone_qty'  => 'float',
        'stone_rate' => 'float',
        'total'      => 'float',
    ];

    public function quotationItem()
    {
        return $this->belongsTo(QuotationItem::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
