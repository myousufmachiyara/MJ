<?php
// app/Models/SaleReturnItem.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleReturnItem extends Model
{
    protected $fillable = [
        'sale_return_id', 'sale_invoice_item_id', 'product_id',
        'item_name', 'item_description', 'barcode_number',
        'gross_weight', 'purity', 'purity_weight', 'col_995',
        'material_type', 'material_rate', 'material_value',
        'making_rate', 'making_value', 'parts_total',
        'taxable_amount', 'vat_percent', 'vat_amount', 'item_total',
    ];

    protected $casts = [
        'gross_weight'   => 'float', 'purity'         => 'float',
        'purity_weight'  => 'float', 'col_995'        => 'float',
        'material_rate'  => 'float', 'material_value' => 'float',
        'making_rate'    => 'float', 'making_value'   => 'float',
        'parts_total'    => 'float', 'taxable_amount' => 'float',
        'vat_percent'    => 'float', 'vat_amount'     => 'float',
        'item_total'     => 'float',
    ];

    public function saleReturn() { return $this->belongsTo(SaleReturn::class); }
    public function parts()      { return $this->hasMany(SaleReturnItemPart::class); }
}