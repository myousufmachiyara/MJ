<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SaleInvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'sale_invoice_id',
        'item_name', 'product_id', 'variation_id', 'item_description',
        'gross_weight', 'purity', 'purity_weight', 'col_995',
        'making_rate', 'making_value',
        'material_type', 'material_rate', 'material_value',
        'taxable_amount', 'vat_percent', 'vat_amount',
        'item_total', 'profit_pct',                 // <-- profit % field
        'remarks',
    ];

    protected $casts = [
        'gross_weight'   => 'decimal:3',
        'purity'         => 'decimal:3',
        'purity_weight'  => 'decimal:3',
        'col_995'        => 'decimal:3',
        'making_rate'    => 'decimal:2',
        'making_value'   => 'decimal:2',
        'material_rate'  => 'decimal:2',
        'material_value' => 'decimal:2',
        'taxable_amount' => 'decimal:2',
        'vat_percent'    => 'decimal:2',
        'vat_amount'     => 'decimal:2',
        'item_total'     => 'decimal:2',
        'profit_pct'     => 'decimal:2',
    ];

    // ========================================
    // RELATIONSHIPS
    // ========================================

    public function invoice()
    {
        return $this->belongsTo(SaleInvoice::class, 'sale_invoice_id');
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
        return $this->hasMany(SaleInvoiceItemPart::class, 'sale_invoice_item_id');
    }

    // ========================================
    // ACCESSORS & ATTRIBUTES
    // ========================================

    public function getDisplayNameAttribute(): string
    {
        return $this->item_name ?: ($this->product->name ?? 'N/A');
    }

    public function getTotalPartsValueAttribute(): float
    {
        return $this->parts->sum('total');
    }

    public function getItemGrandTotalAttribute(): float
    {
        return $this->item_total + $this->total_parts_value;
    }

    public function getPureWeightAttribute(): float
    {
        return $this->purity_weight;
    }

    public function getMaterialTypeLabelAttribute(): string
    {
        return ucfirst($this->material_type);
    }

    public function getVatPercentFormattedAttribute(): string
    {
        return number_format($this->vat_percent, 0) . '%';
    }

    /**
     * Formatted profit % for display (e.g. "12.50%" or "N/A")
     */
    public function getProfitPctFormattedAttribute(): string
    {
        return $this->profit_pct !== null
            ? number_format($this->profit_pct, 2) . '%'
            : 'N/A';
    }

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

    public function getBreakdownAttribute(): array
    {
        return [
            'material'   => $this->material_value,
            'making'     => $this->making_value,
            'parts'      => $this->total_parts_value,
            'vat'        => $this->vat_amount,
            'total'      => $this->item_grand_total,
            'profit_pct' => $this->profit_pct,
        ];
    }

    // ========================================
    // SCOPES
    // ========================================

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

    /** Items with profit above a given threshold */
    public function scopeProfitAbove($query, float $pct)
    {
        return $query->where('profit_pct', '>=', $pct);
    }

    /** Items with profit below a given threshold (includes losses) */
    public function scopeProfitBelow($query, float $pct)
    {
        return $query->where('profit_pct', '<', $pct);
    }

    // ========================================
    // HELPER METHODS
    // ========================================

    /**
     * Recalculate stored totals (does NOT recalculate profit_pct
     * as that requires invoice-level purchase rates).
     */
    public function calculateTotals(): static
    {
        $this->purity_weight  = $this->gross_weight * $this->purity;
        $this->col_995        = $this->purity_weight / 0.995;
        $this->making_value   = $this->gross_weight * $this->making_rate;
        $this->material_value = $this->purity_weight * $this->material_rate;
        $this->taxable_amount = $this->making_value;
        $this->vat_amount     = $this->taxable_amount * ($this->vat_percent / 100);
        $this->item_total     = $this->taxable_amount + $this->material_value + $this->vat_amount;

        return $this;
    }

    public function addPart(array $partData)
    {
        return $this->parts()->create($partData);
    }

    public function getFormattedWeights(): array
    {
        return [
            'gross'   => number_format($this->gross_weight, 3),
            'purity'  => number_format($this->purity, 3),
            'pure'    => number_format($this->purity_weight, 3),
            'col_995' => number_format($this->col_995, 3),
        ];
    }

    // ========================================
    // BOOT
    // ========================================

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($item) {
            $item->parts()->delete();
        });
    }
}