<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleInvoiceAttachment extends Model
{
    protected $fillable = [
        'sale_invoice_id',
        'file_path',
    ];

    public function saleInvoice()
    {
        return $this->belongsTo(SaleInvoice::class);
    }
}