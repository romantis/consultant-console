<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class FinalDefinition extends Model
{
    use HasFactory;
    use HasUlids;

    protected $fillable = [
        'metric_id',
        'user_id',
        'version',
        'status',
        'definition_json',
        'notes',
        'operational_status',
        'operational_status_notes',
        'alignment_summary',
        'implementation_gaps',
        'validation_steps',
        'common_mismatch_causes',
        'executive_takeaway',
        'owner_participant_id',
        'consensus_started_at',
        'signed_off_at',
        'signed_off_by',
    ];

    protected $casts = [
        'definition_json' => 'array',
        'implementation_gaps' => 'array',
        'consensus_started_at' => 'datetime',
        'signed_off_at' => 'datetime',
        'version' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $definition) {
            if (! $definition->isDirty('definition_json')) {
                return;
            }

            $definition->consensus_started_at = self::resolveConsensusStartedAt(
                is_array($definition->definition_json) ? $definition->definition_json : [],
                $definition->consensus_started_at
            );
        });

        static::updated(function (self $definition) {
            $metric = $definition->relationLoaded('metric')
                ? $definition->metric
                : $definition->metric()->first();

            if (! $metric) {
                return;
            }

            if ($definition->wasChanged('owner_participant_id')) {
                $previousParticipant = $definition->getOriginal('owner_participant_id')
                    ? MetricParticipant::find($definition->getOriginal('owner_participant_id'))
                    : null;

                $nextParticipant = $definition->relationLoaded('ownerParticipant')
                    ? $definition->ownerParticipant
                    : $definition->ownerParticipant()->first();

                if (($previousParticipant?->getKey() ?? null) === ($nextParticipant?->getKey() ?? null)) {
                    return;
                }

                AuditLog::create([
                    'organization_id' => $metric->organization_id,
                    'user_id' => Auth::id(),
                    'action' => 'metric.owner.changed',
                    'properties' => array_filter([
                        'metric_id' => (string) $metric->getKey(),
                        'engagement_id' => $metric->engagement_id ? (string) $metric->engagement_id : null,
                        'final_definition_id' => (string) $definition->getKey(),
                        'from_participant_id' => $previousParticipant?->getKey(),
                        'from_user_id' => $previousParticipant?->user_id,
                        'from_user_name' => $previousParticipant?->user?->name,
                        'to_participant_id' => $nextParticipant?->getKey(),
                        'to_user_id' => $nextParticipant?->user_id,
                        'to_user_name' => $nextParticipant?->user?->name,
                    ], static fn ($value) => $value !== null && $value !== ''),
                ]);

                self::syncOrgMetricOwner($metric, $previousParticipant, $nextParticipant);

                return;
            }

            if (! $definition->wasChanged('definition_json')) {
                return;
            }

            $previous = $definition->getOriginal('definition_json') ?? [];
            $next = $definition->definition_json ?? [];

            $previousOwner = self::extractOwnerValue($previous);
            $nextOwner = self::extractOwnerValue($next);

            if ($previousOwner === $nextOwner) {
                return;
            }

            AuditLog::create([
                'organization_id' => $metric->organization_id,
                'user_id' => Auth::id(),
                'action' => 'metric.owner.changed',
                'properties' => array_filter([
                    'metric_id' => (string) $metric->getKey(),
                    'engagement_id' => $metric->engagement_id ? (string) $metric->engagement_id : null,
                    'from' => $previousOwner,
                    'to' => $nextOwner,
                    'final_definition_id' => (string) $definition->getKey(),
                ], static fn ($value) => $value !== null && $value !== ''),
            ]);
        });
    }

    public function metric(): BelongsTo
    {
        return $this->belongsTo(Metric::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function signedOffBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signed_off_by');
    }

    public function ownerParticipant(): BelongsTo
    {
        return $this->belongsTo(MetricParticipant::class, 'owner_participant_id');
    }

    protected static function extractOwnerValue(array $definition): ?string
    {
        foreach ($definition as $key => $field) {
            if (! is_array($field)) {
                continue;
            }

            $label = $field['label'] ?? Str::headline((string) $key);
            $value = $field['value'] ?? null;
            $normalizedKey = Str::of((string) $key)->lower()->value();
            $normalizedLabel = Str::of($label)->lower()->value();

            if (str_contains($normalizedKey, 'owner') || str_contains($normalizedLabel, 'owner')) {
                return is_string($value) && $value !== '' ? $value : null;
            }
        }

        return null;
    }

    /**
     * @param  array<string, array{value?: mixed}>  $definition
     */
    protected static function resolveConsensusStartedAt(array $definition, ?Carbon $current): ?Carbon
    {
        if (self::hasConsensusValues($definition)) {
            return $current ?? now();
        }

        return null;
    }

    /**
     * @param  array<string, array{value?: mixed}>  $definition
     */
    protected static function hasConsensusValues(array $definition): bool
    {
        foreach ($definition as $field) {
            if (! is_array($field)) {
                continue;
            }

            if (filled($field['value'] ?? null)) {
                return true;
            }
        }

        return false;
    }

    private static function syncOrgMetricOwner(
        Metric $metric,
        ?MetricParticipant $previousParticipant,
        ?MetricParticipant $nextParticipant
    ): void {
        if (! $nextParticipant?->user_id) {
            return;
        }

        $orgMetric = $metric->relationLoaded('orgMetric')
            ? $metric->orgMetric
            : $metric->orgMetric()->first();

        if (! $orgMetric) {
            return;
        }

        $currentOwner = OrgMetricParticipant::query()
            ->where('org_metric_id', $orgMetric->getKey())
            ->where('responsibility', 'owner')
            ->orderByDesc('created_at')
            ->first();

        $currentUserId = $currentOwner?->user_id ? (string) $currentOwner->user_id : null;
        $previousUserId = $previousParticipant?->user_id ? (string) $previousParticipant->user_id : null;
        $nextUserId = (string) $nextParticipant->user_id;

        if ($currentOwner && $currentUserId && $currentUserId !== $previousUserId) {
            return;
        }

        if ($currentUserId === $nextUserId) {
            return;
        }

        $payload = [
            'org_metric_id' => $orgMetric->getKey(),
            'responsibility' => 'owner',
            'user_id' => $nextParticipant->user_id,
            'subject_type' => $nextParticipant->subject_type,
            'subject_id' => $nextParticipant->subject_id,
        ];

        if ($currentOwner) {
            $currentOwner->forceFill($payload)->save();

            return;
        }

        OrgMetricParticipant::create($payload);
    }
}
