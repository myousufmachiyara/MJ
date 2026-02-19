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
        'qty', 'rate', 'stone_qty', 'stone_rate', 'total', 'part_description',
    ];

    protected $casts = [
        'qty' => 'decimal:3',
        'rate' => 'decimal:2',
        'stone_qty' => 'decimal:3',
        'stone_rate' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    // ========================================
    // RELATIONSHIPS
    // ========================================
    
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

    /**
     * Get parent invoice through item
     */
    public function invoice()
    {
        return $this->hasOneThrough(
            PurchaseInvoice::class,
            PurchaseInvoiceItem::class,
            'id',
            'id',
            'purchase_invoice_item_id',
            'purchase_invoice_id'
        );
    }

    // ========================================
    // ACCESSORS & ATTRIBUTES
    // ========================================
    
    /**
     * Get display name (custom name or product name)
     */
    public function getDisplayNameAttribute()
    {
        return $this->item_name ?: ($this->product->name ?? 'N/A');
    }

    /**
     * Get part base value (qty * rate)
     */
    public function getPartValueAttribute()
    {
        return $this->qty * $this->rate;
    }

    /**
     * Get stone value (stone_qty * stone_rate)
     */
    public function getStoneValueAttribute()
    {
        return ($this->stone_qty ?? 0) * ($this->stone_rate ?? 0);
    }

    /**
     * Check if part has stones
     */
    public function hasStones()
    {
        return $this->stone_qty > 0;
    }

    /**
     * Get measurement unit
     */
    public function getMeasurementUnitAttribute()
    {
        if ($this->product && $this->product->measurementUnit) {
            return $this->product->measurementUnit->shortcode 
                ?? $this->product->measurementUnit->name 
                ?? 'Pcs';
        }
        return 'Pcs';
    }

    /**
     * Get formatted quantity with unit
     */
    public function getFormattedQtyAttribute()
    {
        return number_format($this->qty, 2) . ' ' . $this->measurement_unit;
    }

    /**
     * Get breakdown of part value
     */
    public function getBreakdownAttribute()
    {
        return [
            'base' => $this->part_value,
            'stone' => $this->stone_value,
            'total' => $this->total,
        ];
    }

    // ========================================
    // SCOPES
    // ========================================
    
    /**
     * Parts with stones
     */
    public function scopeWithStones($query)
    {
        return $query->where('stone_qty', '>', 0);
    }

    /**
     * Parts without stones
     */
    public function scopeWithoutStones($query)
    {
        return $query->where(function($q) {
            $q->whereNull('stone_qty')
              ->orWhere('stone_qty', 0);
        });
    }

    /**
     * Filter by product
     */
    public function scopeForProduct($query, $productId)
    {
        return $query->where('product_id', $productId);
    }

    // ========================================
    // HELPER METHODS
    // ========================================
    
    /**
     * Calculate part total
     */
    public function calculateTotal()
    {
        $baseValue = $this->qty * $this->rate;
        $stoneValue = ($this->stone_qty ?? 0) * ($this->stone_rate ?? 0);
        $this->total = $baseValue + $stoneValue;
        
        return $this;
    }

    /**
     * Get formatted values for display
     */
    public function getFormattedValues()
    {
        return [
            'qty' => number_format($this->qty, 2),
            'rate' => number_format($this->rate, 2),
            'stone_qty' => number_format($this->stone_qty ?? 0, 2),
            'stone_rate' => number_format($this->stone_rate ?? 0, 2),
            'total' => number_format($this->total, 2),
        ];
    }

    // ========================================
    // STATIC METHODS
    // ========================================
    
    /**
     * Create part with automatic total calculation
     */
    public static function createWithTotal(array $data)
    {
        $part = new self($data);
        $part->calculateTotal();
        $part->save();
        
        return $part;
    }
}