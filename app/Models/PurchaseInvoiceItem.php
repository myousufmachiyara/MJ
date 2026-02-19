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
        'gross_weight', 'purity', 'purity_weight', 'col_995',
        'making_rate', 'making_value',
        'material_type', 'material_rate', 'material_value',
        'taxable_amount', 'vat_percent', 'vat_amount',
        'item_total', 'remarks',
    ];

    protected $casts = [
        'gross_weight' => 'decimal:3',
        'purity' => 'decimal:3',
        'purity_weight' => 'decimal:3',
        'col_995' => 'decimal:3',
        'making_rate' => 'decimal:2',
        'making_value' => 'decimal:2',
        'material_rate' => 'decimal:2',
        'material_value' => 'decimal:2',
        'taxable_amount' => 'decimal:2',
        'vat_percent' => 'decimal:2',
        'vat_amount' => 'decimal:2',
        'item_total' => 'decimal:2',
    ];

    // ========================================
    // RELATIONSHIPS
    // ========================================
    
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
     * Get total parts value
     */
    public function getTotalPartsValueAttribute()
    {
        return $this->parts->sum('total');
    }

    /**
     * Get item grand total (item total + parts)
     */
    public function getItemGrandTotalAttribute()
    {
        return $this->item_total + $this->total_parts_value;
    }

    /**
     * Get pure gold/diamond weight
     */
    public function getPureWeightAttribute()
    {
        return $this->purity_weight;
    }

    /**
     * Check if item has parts
     */
    public function hasParts()
    {
        return $this->parts()->exists();
    }

    /**
     * Get material type label
     */
    public function getMaterialTypeLabelAttribute()
    {
        return ucfirst($this->material_type);
    }

    /**
     * Get VAT percentage formatted
     */
    public function getVatPercentFormattedAttribute()
    {
        return number_format($this->vat_percent, 0) . '%';
    }

    /**
     * Check if item is gold
     */
    public function isGold()
    {
        return $this->material_type === 'gold';
    }

    /**
     * Check if item is diamond
     */
    public function isDiamond()
    {
        return $this->material_type === 'diamond';
    }

    /**
     * Get item breakdown for display
     */
    public function getBreakdownAttribute()
    {
        return [
            'material' => $this->material_value,
            'making' => $this->making_value,
            'parts' => $this->total_parts_value,
            'vat' => $this->vat_amount,
            'total' => $this->item_grand_total,
        ];
    }

    // ========================================
    // SCOPES
    // ========================================
    
    /**
     * Filter by material type
     */
    public function scopeMaterialType($query, $type)
    {
        return $query->where('material_type', $type);
    }

    /**
     * Get only gold items
     */
    public function scopeGold($query)
    {
        return $query->where('material_type', 'gold');
    }

    /**
     * Get only diamond items
     */
    public function scopeDiamond($query)
    {
        return $query->where('material_type', 'diamond');
    }

    /**
     * Items with parts
     */
    public function scopeWithParts($query)
    {
        return $query->has('parts');
    }

    /**
     * Items without parts
     */
    public function scopeWithoutParts($query)
    {
        return $query->doesntHave('parts');
    }

    // ========================================
    // HELPER METHODS
    // ========================================
    
    /**
     * Calculate item totals (useful for recalculation)
     */
    public function calculateTotals()
    {
        $this->purity_weight = $this->gross_weight * $this->purity;
        $this->col_995 = $this->purity_weight / 0.995;
        $this->making_value = $this->gross_weight * $this->making_rate;
        $this->material_value = $this->purity_weight * $this->material_rate;
        $this->taxable_amount = $this->making_value;
        $this->vat_amount = $this->taxable_amount * ($this->vat_percent / 100);
        $this->item_total = $this->taxable_amount + $this->material_value + $this->vat_amount;
        
        return $this;
    }

    /**
     * Add a part to this item
     */
    public function addPart(array $partData)
    {
        return $this->parts()->create($partData);
    }

    /**
     * Get formatted weights
     */
    public function getFormattedWeights()
    {
        return [
            'gross' => number_format($this->gross_weight, 3),
            'purity' => number_format($this->purity, 3),
            'pure' => number_format($this->purity_weight, 3),
            'col_995' => number_format($this->col_995, 3),
        ];
    }

    // ========================================
    // BOOT METHOD
    // ========================================
    
    protected static function boot()
    {
        parent::boot();

        // When deleting item, delete its parts
        static::deleting(function ($item) {
            $item->parts()->delete();
        });
    }
}