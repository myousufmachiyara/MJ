<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuotationAttachment extends Model
{
    protected $fillable = [
        'quotation_id',
        'file_path',
    ];

    public function quotation()
    {
        return $this->belongsTo(Quotation::class);
    }
}
