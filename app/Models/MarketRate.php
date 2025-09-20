<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketRate extends Model
{
    protected $fillable = [
        'product_id',
        'variation_id',
        'rate_per_unit',
        'effective_date',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function variation()
    {
        return $this->belongsTo(AttributeValue::class, 'variation_id');
    }
}
