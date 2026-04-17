<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleInvoiceItem extends Model
{
    protected $fillable = [
        'sale_invoice_id',
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
        'gold_rate',
        'diamond_rate',
        'remarks',
        'barcode_number',
        'is_printed',
    ];

    protected $casts = [
        'is_printed' => 'boolean',
    ];

    public function saleInvoice()
    {
        return $this->belongsTo(SaleInvoice::class);
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
        return $this->hasMany(SaleInvoiceItemPart::class);
    }
}