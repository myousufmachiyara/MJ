<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseInvoiceAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_invoice_id',
        'file_path',
    ];

    public function invoice()
    {
        return $this->belongsTo(PurchaseInvoice::class);
    }
}

