<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Consignment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'consignment_no', 'direction', 'partner_id',
        'start_date', 'end_date', 'duration_label',
        'status', 'remarks', 'created_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
    ];

    public function partner()   { return $this->belongsTo(ChartOfAccounts::class, 'partner_id'); }
    public function items()     { return $this->hasMany(ConsignmentItem::class); }
    public function createdBy() { return $this->belongsTo(User::class, 'created_by'); }

    public function isInbound(): bool  { return $this->direction === 'inbound'; }
    public function isOutbound(): bool { return $this->direction === 'outbound'; }

    public static function generateNo(string $direction): string
    {
        $prefix = $direction === 'inbound' ? 'CSG-IN-' : 'CSG-OUT-';
        $last   = self::withTrashed()->where('consignment_no', 'LIKE', $prefix . '%')->orderByDesc('id')->first();
        $next   = $last ? ((int) str_replace($prefix, '', $last->consignment_no)) + 1 : 1;
        return $prefix . str_pad($next, 5, '0', STR_PAD_LEFT);
    }

    public function recalcStatus(): void
    {
        $items    = $this->items()->get();
        $total    = $items->count();
        $sold     = $items->where('item_status', 'sold')->count();
        $returned = $items->where('item_status', 'returned')->count();

        if ($total === 0)                            $status = 'active';
        elseif ($sold === $total)                    $status = 'settled';
        elseif ($sold + $returned === $total)        $status = 'returned';
        elseif ($sold > 0 || $returned > 0)         $status = 'partially_settled';
        elseif ($this->end_date && $this->end_date->isPast()) $status = 'expired';
        else                                         $status = 'active';

        $this->update(['status' => $status]);
    }
}