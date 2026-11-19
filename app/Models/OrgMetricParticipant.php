<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class OrgMetricParticipant extends Model
{
    use HasFactory;
    use HasUlids;

    protected $fillable = [
        'org_metric_id',
        'user_id',
        'responsibility',
        'subject_type',
        'subject_id',
    ];

    public function orgMetric(): BelongsTo
    {
        return $this->belongsTo(OrgMetric::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
