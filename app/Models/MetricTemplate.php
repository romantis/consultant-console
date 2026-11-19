<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class MetricTemplate extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'org_metric_id',
        'canonical_metric_id',
        'key',
        'name',
        'description',
        'form_schema_json',
    ];

    protected $casts = ['form_schema_json' => 'array'];

    protected static function booted(): void
    {
        static::creating(function (self $template) {
            if ($template->canonical_metric_id) {
                return;
            }

            $key = $template->key !== null ? trim((string) $template->key) : '';

            if ($key === '') {
                $key = $template->name ? Str::slug($template->name) : '';
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
                    'name' => $template->name ?? Str::headline($key),
                    'description' => $template->description,
                    'is_active' => true,
                ]
            );

            $template->canonical_metric_id = $canonical->getKey();

            if (! $template->key) {
                $template->key = $canonical->key;
            }

            if (! $template->name) {
                $template->name = $canonical->name;
            }
        });
    }

    public function orgMetric(): BelongsTo
    {
        return $this->belongsTo(OrgMetric::class);
    }

    public function canonicalMetric(): BelongsTo
    {
        return $this->belongsTo(CanonicalMetric::class);
    }
}
