<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConsignmentItemPart extends Model
{
    protected $fillable = [
        'consignment_item_id', 'item_name', 'part_description',
        'qty', 'rate', 'stone_qty', 'stone_rate', 'total',
    ];

    protected $casts = [
        'qty'        => 'float',
        'rate'       => 'float',
        'stone_qty'  => 'float',
        'stone_rate' => 'float',
        'total'      => 'float',
    ];

    public function consignmentItem() { return $this->belongsTo(ConsignmentItem::class); }
}