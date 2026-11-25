<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleInvoiceItem extends Model
{
    protected $fillable = [
        'sale_invoice_id',
        'item_name',
        'product_id',
        'variation_id',
        'item_description',
        'gross_weight',
        'purity',
        'purity_weight',
        'col_995',
        'making_rate',
        'making_value',
        'parts_total',
        'material_rate',
        'material_type',
        'material_value',
        'taxable_amount',
        'vat_percent',
        'vat_amount',
        'item_total',
        'gold_rate',
        'diamond_rate',
        'remarks',
        'barcode_number',
        'is_printed',
    ];

    protected $casts = [
        // Use float so arithmetic and sum() work correctly.
        // decimal:N returns a string in Laravel which breaks calculations.
        'gross_weight'   => 'float',
        'purity'         => 'float',
        'purity_weight'  => 'float',
        'col_995'        => 'float',
        'making_rate'    => 'float',
        'making_value'   => 'float',
        'parts_total'    => 'float',
        'material_rate'  => 'float',
        'material_value' => 'float',
        'taxable_amount' => 'float',
        'vat_percent'    => 'float',
        'vat_amount'     => 'float',
        'item_total'     => 'float',
        'gold_rate'      => 'float',
        'diamond_rate'   => 'float',
        'is_printed'     => 'boolean',
    ];

    // ── Relationships ──────────────────────────────────────────────────────

    public function saleInvoice()
    {
        return $this->belongsTo(SaleInvoice::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function variation()
    {
        return $this->belongsTo(ProductVariation::class);
    }

    public function parts()
    {
        return $this->hasMany(SaleInvoiceItemPart::class);
    }

    // =========================================================================
    // RETURN AVAILABILITY
    //
    // FIX: every "is this barcode already sold?" check across the app
    // (Sale Invoice scan/POS/search, Quotation — which reuses the Sale
    // Invoice endpoints — and Consignment scan) used to look only for the
    // existence of a SaleInvoiceItem with that barcode, with no way to know
    // a SaleReturn later brought it back into stock. These two helpers are
    // the single place that now accounts for returns, so a returned item is
    // treated as available again everywhere consistently, without touching
    // how a genuinely still-sold item behaves.
    // =========================================================================

    /** Has this specific sold line since been returned via Sale Return? */
    public function isReturned(): bool
    {
        return SaleReturnItem::where('sale_invoice_item_id', $this->id)->exists();
    }

    /**
     * The item currently sold under this barcode — i.e. its latest sale,
     * UNLESS that sale has since been returned, in which case the item is
     * back in stock and this returns null (same as a barcode that was never
     * sold at all).
     *
     * FIX (deleted invoice keeps barcode stuck as "sold"): SaleInvoice uses
     * SoftDeletes, so deleting an invoice only sets its deleted_at — the
     * SaleInvoiceItem rows underneath it (which carry barcode_number) are
     * left exactly as they were. Without the whereHas() below, this method
     * would still find that item as the "latest" sale for the barcode and
     * keep reporting it as already sold forever, even though the invoice
     * that sold it no longer exists. whereHas('saleInvoice') requires a
     * still-existing (non-soft-deleted) parent invoice, since the subquery
     * it builds against SaleInvoice automatically carries that model's own
     * SoftDeletingScope — so a genuinely still-sold item is unaffected, and
     * only items whose invoice was deleted are released back to "available".
     */
    public static function currentlySoldFor(?string $barcode): ?self
    {
        if (!$barcode) {
            return null;
        }

        $latest = static::where('barcode_number', $barcode)
            ->whereHas('saleInvoice')
            ->latest()
            ->first();

        if (!$latest || $latest->isReturned()) {
            return null;
        }

        return $latest;
    }
}