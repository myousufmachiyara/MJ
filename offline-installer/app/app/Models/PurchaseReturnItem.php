<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseReturnItem extends Model
{
    protected $fillable = [
        'purchase_return_id', 'purchase_invoice_item_id',
        'item_name', 'product_id', 'item_description',
        'net_weight', 'gross_weight', 'purity', 'purity_weight', 'col_995',
        'material_type', 'material_rate', 'material_value',
        'making_rate', 'making_value', 'parts_total',
        'taxable_amount', 'vat_percent', 'vat_amount', 'item_total',
        'barcode_number',
    ];

    protected $casts = [
        'net_weight'     => 'float',
        'gross_weight'   => 'float',
        'purity'         => 'float',
        'purity_weight'  => 'float',
        'col_995'        => 'float',
        'material_rate'  => 'float',
        'material_value' => 'float',
        'making_rate'    => 'float',
        'making_value'   => 'float',
        'parts_total'    => 'float',
        'taxable_amount' => 'float',
        'vat_percent'    => 'float',
        'vat_amount'     => 'float',
        'item_total'     => 'float',
    ];

    public function purchaseReturn()
    {
        return $this->belongsTo(PurchaseReturn::class);
    }

    public function originalItem()
    {
        return $this->belongsTo(PurchaseInvoiceItem::class, 'purchase_invoice_item_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function parts()
    {
        return $this->hasMany(PurchaseReturnItemPart::class);
    }
}