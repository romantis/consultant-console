<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReconciliationWorkshop extends Model
{
    use HasFactory;
    use HasUlids;

    protected $fillable = [
        'metric_id',
        'engagement_id',
        'session_id',
        'facilitator_id',
        'scheduled_at',
        'participants',
        'decision_snapshot',
        'notes',
        'transcript_url',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'participants' => 'array',
        'decision_snapshot' => 'array',
    ];

    public function engagement(): BelongsTo
    {
        return $this->belongsTo(Engagement::class);
    }

    public function metric(): BelongsTo
    {
        return $this->belongsTo(Metric::class);
    }

    public function facilitator(): BelongsTo
    {
        return $this->belongsTo(Membership::class, 'facilitator_id');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(EngagementWorkshopSession::class, 'session_id');
    }
}
