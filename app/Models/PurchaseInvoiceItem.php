<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseInvoiceItem extends Model
{
    protected $fillable = [
        'purchase_invoice_id',
        'item_name',
        'product_id',
        'variation_id',
        'item_description',
        'net_weight',
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
        // Use float — decimal:N returns a string in Laravel which breaks arithmetic
        'net_weight'     => 'float',
        'gross_weight'   => 'float',
        'purity'         => 'float',
        'purity_weight'  => 'float',
        'col_995'        => 'float',
        'making_rate'    => 'float',
        'making_value'   => 'float',
        'parts_total'    => 'float',
        'material_rate'  => 'float',
        'material_value' => 'float',
        'taxable_amount' => 'float',
        'vat_percent'    => 'float',
        'vat_amount'     => 'float',
        'item_total'     => 'float',
        'gold_rate'      => 'float',
        'diamond_rate'   => 'float',
        'is_printed'     => 'boolean',
    ];

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    /**
     * Primary relationship name — used everywhere in controllers and reports.
     * BOTH names point to the same relationship so nothing breaks regardless
     * of which name is used in with(), whereHas(), or ->purchaseInvoice access.
     */
    public function purchaseInvoice()
    {
        return $this->belongsTo(PurchaseInvoice::class, 'purchase_invoice_id');
    }

    /**
     * Alias kept for any legacy code that calls ->invoice or whereHas('invoice').
     */
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
        return $this->hasMany(PurchaseInvoiceItemPart::class);
    }
}