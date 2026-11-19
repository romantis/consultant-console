<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Discrepancy extends Model
{
    use HasFactory;
    use HasUlids;

    protected $fillable = [
        'metric_id',
        'engagement_id',
        'field_key',
        'rule_type',
        'severity',
        'status',
        'description',
        'values_snapshot',
        'detected_at',
        'resolved_at',
    ];

    protected $casts = [
        'values_snapshot' => 'array',
        'detected_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function metric(): BelongsTo
    {
        return $this->belongsTo(Metric::class);
    }

    public function engagement(): BelongsTo
    {
        return $this->belongsTo(Engagement::class);
    }

    public function scopeOpen($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('resolved_at')->orWhere('status', 'Open');
        });
    }

    public function markResolved(): void
    {
        $this->forceFill([
            'status' => 'Resolved',
            'resolved_at' => now(),
        ])->save();
    }
}
