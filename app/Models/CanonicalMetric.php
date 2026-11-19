<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CanonicalMetric extends Model
{
    use HasFactory;
    use HasUlids;

    protected $fillable = [
        'key',
        'name',
        'description',
        'default_metric_template_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function templates(): HasMany
    {
        return $this->hasMany(MetricTemplate::class);
    }

    public function defaultTemplate(): BelongsTo
    {
        return $this->belongsTo(MetricTemplate::class, 'default_metric_template_id');
    }

    public function orgMetrics(): HasMany
    {
        return $this->hasMany(OrgMetric::class);
    }
}
