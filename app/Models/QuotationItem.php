<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuotationItem extends Model
{
    protected $fillable = [
        'quotation_id',
        'item_name',
        'product_id',
        'variation_id',
        'item_description',
        'gross_weight',
        'purity',
        'purity_weight',
        'col_995',
        'making_rate',
        'making_value',
        'parts_total',
        'material_rate',
        'material_type',
        'material_value',
        'taxable_amount',
        'vat_percent',
        'vat_amount',
        'item_total',
        'discount_type',
        'discount_value',
        'discount_amount',
        'gold_rate',
        'diamond_rate',
        'remarks',
        'barcode_number',
    ];

    protected $casts = [
        'gross_weight'    => 'float',
        'purity'          => 'float',
        'purity_weight'   => 'float',
        'col_995'         => 'float',
        'making_rate'     => 'float',
        'making_value'    => 'float',
        'parts_total'     => 'float',
        'material_rate'   => 'float',
        'material_value'  => 'float',
        'taxable_amount'  => 'float',
        'vat_percent'     => 'float',
        'vat_amount'      => 'float',
        'item_total'      => 'float',
        'discount_value'  => 'float',
        'discount_amount' => 'float',
        'gold_rate'       => 'float',
        'diamond_rate'    => 'float',
    ];

    public function quotation()
    {
        return $this->belongsTo(Quotation::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function variation()
    {
        return $this->belongsTo(ProductVariation::class);
    }

    public function parts()
    {
        return $this->hasMany(QuotationItemPart::class);
    }
}
