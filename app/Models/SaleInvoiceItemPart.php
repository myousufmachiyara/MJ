<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SaleInvoiceItemPart extends Model
{
    use HasFactory;

    protected $fillable = [
        'sale_invoice_item_id',
        'product_id', 'variation_id', 'item_name',
        'qty', 'rate', 'stone_qty', 'stone_rate', 'total', 'part_description',
    ];

    protected $casts = [
        'qty'        => 'decimal:3',
        'rate'       => 'decimal:2',
        'stone_qty'  => 'decimal:3',
        'stone_rate' => 'decimal:2',
        'total'      => 'decimal:2',
    ];

    // ========================================
    // RELATIONSHIPS
    // ========================================

    public function item()
    {
        return $this->belongsTo(SaleInvoiceItem::class, 'sale_invoice_item_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function variation()
    {
        return $this->belongsTo(ProductVariation::class);
    }

    public function invoice()
    {
        return $this->hasOneThrough(
            SaleInvoice::class,
            SaleInvoiceItem::class,
            'id',
            'id',
            'sale_invoice_item_id',
            'sale_invoice_id'
        );
    }

    // ========================================
    // ACCESSORS & ATTRIBUTES
    // ========================================

    public function getDisplayNameAttribute(): string
    {
        return $this->item_name ?: ($this->product->name ?? 'N/A');
    }

    public function getPartValueAttribute(): float
    {
        return $this->qty * $this->rate;
    }

    public function getStoneValueAttribute(): float
    {
        return ($this->stone_qty ?? 0) * ($this->stone_rate ?? 0);
    }

    public function hasStones(): bool
    {
        return $this->stone_qty > 0;
    }

    public function getMeasurementUnitAttribute(): string
    {
        if ($this->product && $this->product->measurementUnit) {
            return $this->product->measurementUnit->shortcode
                ?? $this->product->measurementUnit->name
                ?? 'Pcs';
        }
        return 'Pcs';
    }

    public function getFormattedQtyAttribute(): string
    {
        return number_format($this->qty, 2) . ' ' . $this->measurement_unit;
    }

    public function getBreakdownAttribute(): array
    {
        return [
            'base'  => $this->part_value,
            'stone' => $this->stone_value,
            'total' => $this->total,
        ];
    }

    // ========================================
    // SCOPES
    // ========================================

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

    // ========================================
    // HELPER METHODS
    // ========================================

    public function calculateTotal(): static
    {
        $this->total = ($this->qty * $this->rate)
            + (($this->stone_qty ?? 0) * ($this->stone_rate ?? 0));

        return $this;
    }

    public function getFormattedValues(): array
    {
        return [
            'qty'        => number_format($this->qty, 2),
            'rate'       => number_format($this->rate, 2),
            'stone_qty'  => number_format($this->stone_qty ?? 0, 2),
            'stone_rate' => number_format($this->stone_rate ?? 0, 2),
            'total'      => number_format($this->total, 2),
        ];
    }

    // ========================================
    // STATIC METHODS
    // ========================================

    public static function createWithTotal(array $data): static
    {
        $part = new self($data);
        $part->calculateTotal();
        $part->save();

        return $part;
    }
}