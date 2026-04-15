<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseInvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_invoice_id',
        'item_name', 'product_id', 'variation_id', 'item_description',

        // Weight columns
        'net_weight',      // user-entered weight (.net-weight in blade)
        'gross_weight',    // calculated: net_wt + (diamondCTS/5) + (stoneCTS/5)  (.gross-weight in blade)
        'purity',
        'purity_weight',   // net_weight × purity
        'col_995',         // purity_weight / 0.995

        // Making
        'making_rate',
        'making_value',    // net_weight × making_rate

        // Material
        'material_type',   // 'gold' | 'diamond'
        'material_rate',   // gold → AED/gram ; diamond → AED/Ct
        'material_value',  // material_rate × purity_weight

        // Parts
        'parts_total',     // Σ part totals (stored for reference; NOT journalized)

        // Totals
        'taxable_amount',  // making_value + parts_total
        'vat_percent',
        'vat_amount',      // taxable_amount × (vat_percent / 100)
        'item_total',      // material_value + taxable_amount + vat_amount

        'remarks',
        'barcode_number',
        'is_printed',
    ];

    protected $casts = [
        // Weight
        'net_weight'    => 'decimal:4',
        'gross_weight'  => 'decimal:4',
        'purity'        => 'decimal:4',
        'purity_weight' => 'decimal:4',
        'col_995'       => 'decimal:4',

        // Making
        'making_rate'   => 'decimal:4',
        'making_value'  => 'decimal:4',

        // Material
        'material_rate'  => 'decimal:4',  // AED/gram needs 4dp
        'material_value' => 'decimal:4',

        // Parts
        'parts_total'    => 'decimal:4',

        // Totals
        'taxable_amount' => 'decimal:4',
        'vat_percent'    => 'decimal:4',
        'vat_amount'     => 'decimal:4',
        'item_total'     => 'decimal:4',

        'is_printed'     => 'boolean',
    ];

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    public function invoice()
    {
        return $this->belongsTo(PurchaseInvoice::class, 'purchase_invoice_id');
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
        return $this->hasMany(PurchaseInvoiceItemPart::class, 'purchase_invoice_item_id');
    }

    // =========================================================================
    // ACCESSORS
    // =========================================================================

    /** Custom name or product name fallback */
    public function getDisplayNameAttribute(): string
    {
        return $this->item_name ?: ($this->product->name ?? 'N/A');
    }

    /** Sum of all part row totals */
    public function getTotalPartsValueAttribute(): float
    {
        return (float) $this->parts->sum('total');
    }

    /**
     * Item grand total = item_total + parts total.
     * item_total = material + making + vat (no parts).
     * Grand total adds parts on top.
     */
    public function getItemGrandTotalAttribute(): float
    {
        return (float) $this->item_total + $this->total_parts_value;
    }

    /** Diamond part value: Σ (qty × rate) */
    public function getDiamondPartsValueAttribute(): float
    {
        return (float) $this->parts->sum(fn($p) => $p->qty * $p->rate);
    }

    /** Stone part value: Σ (stone_qty × stone_rate) */
    public function getStonePartsValueAttribute(): float
    {
        return (float) $this->parts->sum(fn($p) => ($p->stone_qty ?? 0) * ($p->stone_rate ?? 0));
    }

    public function getMaterialTypeLabelAttribute(): string
    {
        return ucfirst($this->material_type);
    }

    public function getVatPercentFormattedAttribute(): string
    {
        return number_format($this->vat_percent, 0) . '%';
    }

    public function getBreakdownAttribute(): array
    {
        return [
            'material'     => $this->material_value,
            'making'       => $this->making_value,
            'parts'        => $this->total_parts_value,
            'vat'          => $this->vat_amount,
            'item_total'   => $this->item_total,
            'grand_total'  => $this->item_grand_total,
        ];
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    public function isGold(): bool
    {
        return $this->material_type === 'gold';
    }

    public function isDiamond(): bool
    {
        return $this->material_type === 'diamond';
    }

    public function hasParts(): bool
    {
        return $this->parts()->exists();
    }

    /**
     * Recalculate all derived fields from stored net_weight / purity / rates.
     * Mirrors blade calculateRow() exactly.
     *
     * Formula:
     *   purity_weight  = net_weight × purity
     *   col_995        = purity_weight / 0.995
     *   making_value   = net_weight × making_rate     ← net_weight, NOT gross_weight
     *   material_value = material_rate × purity_weight
     *   parts_total    = loaded from related parts
     *   taxable        = making_value + parts_total
     *   vat_amount     = taxable × (vat_percent / 100)
     *   item_total     = material_value + taxable + vat_amount
     */
    public function recalculate(): static
    {
        $this->loadMissing('parts');

        $this->purity_weight  = $this->net_weight * $this->purity;
        $this->col_995        = $this->purity_weight > 0 ? $this->purity_weight / 0.995 : 0;
        $this->making_value   = $this->net_weight * $this->making_rate;
        $this->material_value = $this->material_rate * $this->purity_weight;

        $partsTotal = (float) $this->parts->sum('total');

        $this->parts_total    = $partsTotal;
        $this->taxable_amount = $this->making_value + $partsTotal;
        $this->vat_amount     = $this->taxable_amount * ($this->vat_percent / 100);
        $this->item_total     = $this->material_value + $this->taxable_amount + $this->vat_amount;

        return $this;
    }

    public function getFormattedWeights(): array
    {
        return [
            'net_weight'    => number_format($this->net_weight, 3),
            'gross_weight'  => number_format($this->gross_weight, 3),
            'purity'        => number_format($this->purity, 3),
            'purity_weight' => number_format($this->purity_weight, 3),
            'col_995'       => number_format($this->col_995, 3),
        ];
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeMaterialType($query, $type)
    {
        return $query->where('material_type', $type);
    }

    public function scopeGold($query)
    {
        return $query->where('material_type', 'gold');
    }

    public function scopeDiamond($query)
    {
        return $query->where('material_type', 'diamond');
    }

    public function scopeWithParts($query)
    {
        return $query->has('parts');
    }

    public function scopeWithoutParts($query)
    {
        return $query->doesntHave('parts');
    }

    public function scopePrinted($query)
    {
        return $query->where('is_printed', true);
    }

    public function scopeNotPrinted($query)
    {
        return $query->where('is_printed', false);
    }

    // =========================================================================
    // BOOT
    // =========================================================================

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($item) {
            $item->parts()->delete();
        });
    }
}