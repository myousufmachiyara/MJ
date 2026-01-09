<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseInvoice_1_Item extends Model
{
    protected $table = 'purchase_invoices_1_items';

    protected $fillable = [
        'purchase_invoice_id',
        'item_description',
        'purity',
        'gross_weight',
        'purity_weight',
        'making_rate',
        'metal_value',
        'taxable_amount',
        'vat_percent',
    ];

    /* ================= RELATIONS ================= */

    public function invoice()
    {
        return $this->belongsTo(PurchaseInvoice_1::class, 'purchase_invoice_id');
    }
}
