<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseInvoiceItemPart extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_invoice_item_id',
        'product_id', 'variation_id', 'item_name',
        'part_description',
        'qty',        // Diamond CTS
        'rate',       // Diamond rate (AED/Ct)
        'stone_qty',  // Stone carats
        'stone_rate', // Stone rate (AED/Ct)
        'total',      // (qty × rate) + (stone_qty × stone_rate)
    ];

    protected $casts = [
        'qty'        => 'decimal:3',
        'rate'       => 'decimal:2',
        'stone_qty'  => 'decimal:3',
        'stone_rate' => 'decimal:2',
        'total'      => 'decimal:2',
    ];

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    public function item()
    {
        return $this->belongsTo(PurchaseInvoiceItem::class, 'purchase_invoice_item_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function variation()
    {
        return $this->belongsTo(ProductVariation::class);
    }

    // =========================================================================
    // ACCESSORS
    // =========================================================================

    /** Custom name or product name fallback */
    public function getDisplayNameAttribute(): string
    {
        return $this->item_name ?: ($this->product->name ?? 'N/A');
    }

    /** Diamond value: qty × rate */
    public function getPartValueAttribute(): float
    {
        return (float) ($this->qty * $this->rate);
    }

    /** Stone value: stone_qty × stone_rate */
    public function getStoneValueAttribute(): float
    {
        return (float) (($this->stone_qty ?? 0) * ($this->stone_rate ?? 0));
    }

    public function hasStones(): bool
    {
        return ($this->stone_qty ?? 0) > 0;
    }

    public function getMeasurementUnitAttribute(): string
    {
        if ($this->product && $this->product->measurementUnit) {
            return $this->product->measurementUnit->shortcode
                ?? $this->product->measurementUnit->name
                ?? 'Ct.';
        }
        return 'Ct.';
    }

    public function getFormattedQtyAttribute(): string
    {
        return number_format($this->qty, 2) . ' ' . $this->measurement_unit;
    }

    public function getBreakdownAttribute(): array
    {
        return [
            'diamond_value' => $this->part_value,
            'stone_value'   => $this->stone_value,
            'total'         => $this->total,
        ];
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Recalculate total from qty/rate/stone values.
     * Formula: total = (qty × rate) + (stone_qty × stone_rate)
     */
    public function recalculate(): static
    {
        $this->total = ($this->qty * $this->rate)
            + (($this->stone_qty ?? 0) * ($this->stone_rate ?? 0));

        return $this;
    }

    public function getFormattedValues(): array
    {
        return [
            'qty'        => number_format($this->qty, 3),
            'rate'       => number_format($this->rate, 2),
            'stone_qty'  => number_format($this->stone_qty ?? 0, 3),
            'stone_rate' => number_format($this->stone_rate ?? 0, 2),
            'total'      => number_format($this->total, 2),
        ];
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeWithStones($query)
    {
        return $query->where('stone_qty', '>', 0);
    }

    public function scopeWithoutStones($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('stone_qty')->orWhere('stone_qty', 0);
        });
    }

    public function scopeForProduct($query, $productId)
    {
        return $query->where('product_id', $productId);
    }

    // =========================================================================
    // STATIC
    // =========================================================================

    /**
     * Create a part and auto-calculate its total.
     */
    public static function createWithTotal(array $data): static
    {
        $part = new self($data);
        $part->recalculate();
        $part->save();

        return $part;
    }
}