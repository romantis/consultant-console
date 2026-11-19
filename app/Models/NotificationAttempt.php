<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationAttempt extends Model
{
    use HasFactory;
    use HasUlids;

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SKIPPED = 'skipped';

    protected $fillable = [
        'organization_id',
        'engagement_id',
        'session_id',
        'metric_id',
        'form_assignment_id',
        'notification_id',
        'channel',
        'provider_response',
        'status',
        'attempted_at',
    ];

    protected $casts = [
        'provider_response' => 'array',
        'attempted_at' => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function engagement(): BelongsTo
    {
        return $this->belongsTo(Engagement::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(EngagementWorkshopSession::class, 'session_id');
    }

    public function metric(): BelongsTo
    {
        return $this->belongsTo(Metric::class);
    }

    public function formAssignment(): BelongsTo
    {
        return $this->belongsTo(FormAssignment::class);
    }

    public function reminder(): BelongsTo
    {
        return $this->belongsTo(ReminderSchedule::class, 'notification_id');
    }
}
