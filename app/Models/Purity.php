<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Purity extends Model
{
    protected $fillable = ['label', 'value', 'sort_order'];

    protected $casts = [
        'value' => 'decimal:4',
    ];

    // Always return in sort_order then id order
    protected static function booted(): void
    {
        static::addGlobalScope('ordered', fn($q) => $q->orderBy('sort_order')->orderBy('id'));
    }
}