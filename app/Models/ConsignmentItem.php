<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConsignmentItem extends Model
{
    protected $fillable = [
        'consignment_id', 'item_name', 'product_id', 'item_description',
        'barcode_number', 'is_printed',
        'gross_weight', 'purity', 'purity_weight', 'col_995',
        'making_rate', 'making_value',
        'material_type', 'material_rate', 'material_value',
        'parts_total', 'taxable_amount', 'vat_percent', 'vat_amount',
        'agreed_value', 'item_status',
        'settled_by_sale_invoice_id', 'settled_date',
    ];

    protected $casts = [
        // Always float — decimal:N returns string in Laravel and breaks arithmetic
        'gross_weight'   => 'float',
        'purity'         => 'float',
        'purity_weight'  => 'float',
        'col_995'        => 'float',
        'making_rate'    => 'float',
        'making_value'   => 'float',
        'material_rate'  => 'float',
        'material_value' => 'float',
        'parts_total'    => 'float',
        'taxable_amount' => 'float',
        'vat_percent'    => 'float',
        'vat_amount'     => 'float',
        'agreed_value'   => 'float',
        'is_printed'     => 'boolean',
        'settled_date'   => 'date',
    ];

    public function consignment()          { return $this->belongsTo(Consignment::class); }
    public function product()              { return $this->belongsTo(Product::class); }
    public function parts()                { return $this->hasMany(ConsignmentItemPart::class); }
    public function settledBySaleInvoice() { return $this->belongsTo(SaleInvoice::class, 'settled_by_sale_invoice_id'); }
}