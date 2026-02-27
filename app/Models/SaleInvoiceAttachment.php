<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleInvoiceAttachment extends Model
{
    protected $fillable = [
        'sale_invoice_id',
        'file_path',
    ];

    public function invoice()
    {
        return $this->belongsTo(SaleInvoice::class, 'sale_invoice_id');
    }
}