<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MetricParticipant extends Model
{
    use HasFactory;
    use HasUlids;

    protected $fillable = [
        'metric_id',
        'engagement_participant_id',
        'user_id',
        'subject_type',
        'subject_id',
        'responsibility',
        'role_tags_json',
    ];

    protected $casts = [
        'role_tags_json' => 'array',
    ];

    public function metric(): BelongsTo
    {
        return $this->belongsTo(Metric::class);
    }

    public function engagementParticipant(): BelongsTo
    {
        return $this->belongsTo(EngagementParticipant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(FormAssignment::class, 'metric_participant_id');
    }
}
