<?php

namespace App\Models;

use App\Casts\EngagementStatusCast;
use App\Enums\EngagementStatus;
use App\Support\StateGuards\EngagementStateGuard;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Engagement extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'title',
        'status',
        'starts_on',
        'ends_on',
        'finalized_at',
        'review_cadence',
        'next_review_on',
        'repository_driver',
        'repository_reference',
        'repository_notes',
        'stakeholders_json',
        'onboarding_checklist_json',
    ];

    protected $casts = [
        'status' => EngagementStatusCast::class,
        'starts_on' => 'date',
        'ends_on' => 'date',
        'finalized_at' => 'datetime',
        'next_review_on' => 'date',
        'stakeholders_json' => 'array',
        'onboarding_checklist_json' => 'array',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $engagement) {
            if (! $engagement->isDirty('status')) {
                return;
            }

            $original = $engagement->getRawOriginal('status');
            $from = $original ? EngagementStatus::fromLegacy($original) : null;
            $next = $engagement->status instanceof EngagementStatus
                ? $engagement->status
                : EngagementStatus::fromLegacy($engagement->status);

            EngagementStateGuard::ensureValid($from, $next);
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function metrics(): HasMany
    {
        return $this->hasMany(Metric::class);
    }

    public function forms(): HasMany
    {
        return $this->hasMany(Form::class);
    }

    public function discrepancies(): HasMany
    {
        return $this->hasMany(Discrepancy::class);
    }

    public function workshops(): HasMany
    {
        return $this->hasMany(ReconciliationWorkshop::class);
    }

    public function participants(): HasMany
    {
        return $this->hasMany(EngagementParticipant::class);
    }

    public function workshopSessions(): HasMany
    {
        return $this->hasMany(EngagementWorkshopSession::class);
    }
}
