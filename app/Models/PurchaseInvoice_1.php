<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseInvoice_1 extends Model
{
     use SoftDeletes;

    protected $table = 'purchase_invoices_1';

    protected $fillable = [
        'invoice_no',
        'vendor_id',
        'invoice_date',
        'remarks',
        'net_amount',

        // Payment
        'payment_method',

        // Cheque
        'cheque_no',
        'cheque_date',
        'bank_name',
        'cheque_amount',

        // Material + Making
        'material_weight',
        'material_purity',
        'material_value',
        'making_charges',

        'created_by',
    ];

    /* ================= RELATIONS ================= */

    public function items()
    {
        return $this->hasMany(PurchaseInvoice_1_Item::class, 'purchase_invoice_id');
    }

    public function vendor()
    {
        return $this->belongsTo(ChartOfAccounts::class, 'vendor_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

}
