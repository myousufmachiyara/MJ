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

        // Currency
        'currency',
        'exchange_rate',
        'net_amount',
        'net_amount_aed',

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

    protected $casts = [
        'invoice_date'   => 'date',
        'cheque_date'    => 'date',

        'exchange_rate'  => 'decimal:4',
        'net_amount'     => 'decimal:2',
        'net_amount_aed' => 'decimal:2',

        'material_weight' => 'decimal:3',
        'material_purity' => 'decimal:2',
        'material_value'  => 'decimal:2',
        'making_charges'  => 'decimal:2',
        'cheque_amount'   => 'decimal:2',
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

    /* ================= HELPERS ================= */

    public function isUsd()
    {
        return $this->currency === 'USD';
    }

    public function displayNetAmount()
    {
        return $this->currency === 'USD'
            ? number_format($this->net_amount, 2) . ' USD'
            : number_format($this->net_amount_aed ?? $this->net_amount, 2) . ' AED';
    }
}
