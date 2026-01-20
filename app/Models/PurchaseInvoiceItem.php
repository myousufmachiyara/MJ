<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseInvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_invoice_id',

        // Product
        'item_name',
        'product_id',
        'variation_id',
        'item_description',

        // Weights & purity
        'gross_weight',
        'purity',
        'purity_weight',
        'col_995',

        // Making
        'making_rate',
        'making_value',

        // Metal
        'material_rate',
        'material_type',
        'material_value',

        // Tax
        'taxable_amount',
        'vat_percent',
        'vat_amount',

        // Total
        'item_total',

        // Meta
        'remarks',
    ];

    public function invoice()
    {
        return $this->belongsTo(PurchaseInvoice::class, 'purchase_invoice_id');
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
        return $this->hasMany(PurchaseInvoiceItemPart::class, 'purchase_invoice_item_id');
    }
}

