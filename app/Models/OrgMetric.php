<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class OrgMetric extends Model
{
    use HasFactory;
    use HasUlids;

    protected $fillable = [
        'organization_id',
        'canonical_metric_id',
        'default_metric_template_id',
        'key',
        'slug',
        'name',
        'alias',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $orgMetric) {
            if ($orgMetric->canonical_metric_id) {
                return;
            }

            $key = $orgMetric->key !== null ? trim((string) $orgMetric->key) : '';

            if ($key === '') {
                $key = $orgMetric->name ? Str::slug($orgMetric->name) : '';
            }

            if ($key === '') {
                $key = 'metric-'.Str::lower(Str::random(6));
            }

            if (strlen($key) > 120) {
                $key = Str::limit($key, 120, '');
            }

            $canonical = CanonicalMetric::firstOrCreate(
                ['key' => $key],
                [
                    'name' => $orgMetric->name ?? Str::headline($key),
                    'description' => null,
                    'is_active' => true,
                ]
            );

            $orgMetric->canonical_metric_id = $canonical->getKey();

            if (! $orgMetric->key) {
                $orgMetric->key = $canonical->key;
            }

            if (! $orgMetric->name) {
                $orgMetric->name = $canonical->name;
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function canonicalMetric(): BelongsTo
    {
        return $this->belongsTo(CanonicalMetric::class);
    }

    public function defaultTemplate(): BelongsTo
    {
        return $this->belongsTo(MetricTemplate::class, 'default_metric_template_id');
    }

    public function metrics(): HasMany
    {
        return $this->hasMany(Metric::class);
    }

    public function templates(): HasMany
    {
        return $this->hasMany(MetricTemplate::class);
    }

    public function participants(): HasMany
    {
        return $this->hasMany(OrgMetricParticipant::class);
    }

    public function finalDefinitions(): HasMany
    {
        return $this->hasMany(OrgFinalDefinition::class);
    }
}
