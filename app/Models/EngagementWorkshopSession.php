<?php

namespace App\Models;

use App\Enums\EngagementWorkshopSessionStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EngagementWorkshopSession extends Model
{
    use HasFactory;
    use HasUlids;

    protected $fillable = [
        'organization_id',
        'engagement_id',
        'facilitator_membership_id',
        'starts_at',
        'ends_at',
        'timezone',
        'status',
        'notes',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'status' => EngagementWorkshopSessionStatus::class,
    ];

    public function engagement(): BelongsTo
    {
        return $this->belongsTo(Engagement::class);
    }

    public function facilitatorMembership(): BelongsTo
    {
        return $this->belongsTo(Membership::class, 'facilitator_membership_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function metrics(): HasMany
    {
        return $this->hasMany(EngagementWorkshopSessionMetric::class, 'session_id');
    }

    public function metricScope(): BelongsToMany
    {
        return $this->belongsToMany(
            Metric::class,
            'engagement_workshop_session_metrics',
            'session_id',
            'metric_id'
        )->withTimestamps();
    }

    public function participants(): HasMany
    {
        return $this->hasMany(EngagementWorkshopSessionParticipant::class, 'session_id');
    }

    public function participantScope(): BelongsToMany
    {
        return $this->belongsToMany(
            EngagementParticipant::class,
            'engagement_workshop_session_participants',
            'session_id',
            'engagement_participant_id'
        )->withTimestamps();
    }

    public function workshops(): HasMany
    {
        return $this->hasMany(ReconciliationWorkshop::class, 'session_id');
    }
}
