<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseReturnItemPart extends Model
{
    protected $fillable = [
        'purchase_return_item_id', 'item_name', 'part_description',
        'qty', 'rate', 'stone_qty', 'stone_rate', 'certification_charges', 'total',
    ];

    protected $casts = [
        'qty'                   => 'decimal:3',
        'rate'                  => 'decimal:4',
        'stone_qty'             => 'decimal:3',
        'stone_rate'            => 'decimal:4',
        'certification_charges' => 'decimal:4',
        'total'                 => 'decimal:4',
    ];

    public function returnItem()
    {
        return $this->belongsTo(PurchaseReturnItem::class);
    }
}