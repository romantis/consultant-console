<?php

namespace App\Models;

use App\Enums\MetricStatus;
use App\Support\StateGuards\MetricStateGuard;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Metric extends Model
{
    use HasFactory;
    use HasUlids;
    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'engagement_id',
        'metric_template_id',
        'org_metric_id',
        'key',
        'slug',
        'name',
        'status',
        'last_submitted_at',
        'recompute_status',
        'recompute_queued_at',
        'recompute_finished_at',
        'definitions_changed_at',
        'brief_source_revision',
        'draft_generation_status',
        'draft_generation_error',
    ];

    protected $casts = [
        'status' => MetricStatus::class,
        'last_submitted_at' => 'datetime',
        'recompute_queued_at' => 'datetime',
        'recompute_finished_at' => 'datetime',
        'definitions_changed_at' => 'datetime',
        'brief_source_revision' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $metric) {
            if ($metric->org_metric_id || ! $metric->organization_id) {
                return;
            }

            $template = $metric->metric_template_id
                ? MetricTemplate::query()->find($metric->metric_template_id)
                : null;

            $key = $template?->key ?? $metric->key ?? $metric->name ?? null;

            if (! $key) {
                $key = 'metric-'.Str::lower(Str::random(6));
            }

            if (strlen((string) $key) > 120) {
                $key = Str::limit((string) $key, 120, '');
            }

            $orgMetric = OrgMetric::firstOrCreate(
                [
                    'organization_id' => $metric->organization_id,
                    'key' => $key,
                ],
                [
                    'name' => $template?->name ?? $metric->name ?? Str::headline((string) $key),
                    'slug' => $metric->slug ?: Str::slug((string) $key),
                ]
            );

            $metric->org_metric_id = $orgMetric->getKey();

            if ($template && ! $template->org_metric_id) {
                $template->forceFill(['org_metric_id' => $orgMetric->getKey()])->save();
            }
        });

        static::updating(function (self $metric) {
            if (! $metric->isDirty('status')) {
                return;
            }

            $original = $metric->getOriginal('status');
            $from = $original ? MetricStatus::from($original->value) : null;
            $next = $metric->status instanceof MetricStatus
                ? $metric->status
                : MetricStatus::from($metric->status);

            MetricStateGuard::ensureValid($from, $next);
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function engagement(): BelongsTo
    {
        return $this->belongsTo(Engagement::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(MetricTemplate::class, 'metric_template_id');
    }

    public function orgMetric(): BelongsTo
    {
        return $this->belongsTo(OrgMetric::class);
    }

    public function definitions(): HasMany
    {
        return $this->hasMany(MetricDefinition::class)->orderBy('id');
    }

    public function discrepancies(): HasMany
    {
        return $this->hasMany(Discrepancy::class)->orderBy('field_key')->orderBy('id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(MetricParticipant::class);
    }

    public function workshops(): HasMany
    {
        return $this->hasMany(ReconciliationWorkshop::class);
    }

    public function finalDefinition(): HasOne
    {
        return $this->hasOne(FinalDefinition::class)
            ->orderByDesc('version')
            ->orderByDesc('final_definitions.created_at');
    }

    /**
     * @return HasOne<MetricAlignmentDraft, $this>
     */
    public function alignmentDraft(): HasOne
    {
        return $this->hasOne(MetricAlignmentDraft::class);
    }

    /**
     * @return HasOne<MetricFacilitationBrief, $this>
     */
    public function facilitationBrief(): HasOne
    {
        return $this->hasOne(MetricFacilitationBrief::class);
    }

    public function changeProposals(): HasMany
    {
        return $this->hasMany(ChangeProposal::class);
    }

    public function workshopSessionMetrics(): HasMany
    {
        return $this->hasMany(EngagementWorkshopSessionMetric::class);
    }

    public function scopeForOrganization($query, Organization $organization)
    {
        return $query->where('organization_id', $organization->id);
    }

    public function markStatus(MetricStatus $status): void
    {
        if ($this->status === $status) {
            return;
        }

        $this->forceFill(['status' => $status])->save();
    }

    public function incrementBriefSourceRevision(): void
    {
        $this->increment('brief_source_revision');
    }

    public function touchDefinitionsChanged(): void
    {
        $this->increment('brief_source_revision', 1, [
            'definitions_changed_at' => now(),
        ]);
    }
}
