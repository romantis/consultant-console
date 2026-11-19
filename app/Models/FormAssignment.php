<?php

namespace App\Models;

use App\Enums\FormAssignmentStatus;
use App\Support\StateGuards\FormAssignmentStateGuard;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\Pivot;

class FormAssignment extends Pivot
{
    use HasFactory;
    use HasUlids;

    protected $table = 'form_assignment';

    protected $fillable = [
        'form_id',
        'metric_id',
        'metric_participant_id',
        'user_id',
        'status',
        'responsibility',
        'role_tags_json',
        'due_at',
        'reminded_48h_at',
        'reminded_24h_at',
        'reminded_4h_at',
    ];

    protected $casts = [
        'status' => FormAssignmentStatus::class,
        'due_at' => 'datetime',
        'reminded_48h_at' => 'datetime',
        'reminded_24h_at' => 'datetime',
        'reminded_4h_at' => 'datetime',
        'role_tags_json' => 'array',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $assignment) {
            if (! $assignment->isDirty('status')) {
                return;
            }

            $original = $assignment->getOriginal('status');
            $from = $original instanceof FormAssignmentStatus
                ? $original
                : ($original ? FormAssignmentStatus::from($original) : null);
            $next = $assignment->status instanceof FormAssignmentStatus
                ? $assignment->status
                : FormAssignmentStatus::from($assignment->status);

            $metric = $assignment->relationLoaded('metric')
                ? $assignment->metric
                : $assignment->metric()->first();

            FormAssignmentStateGuard::ensureValid($from, $next, $metric);
        });
    }

    /** @return BelongsTo<Form, $this> */
    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    public function metric()
    {
        return $this->belongsTo(Metric::class);
    }

    public function participant()
    {
        return $this->belongsTo(MetricParticipant::class, 'metric_participant_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function submission(): HasOne
    {
        return $this->hasOne(FormSubmission::class, 'form_id', 'form_id');
    }
}
