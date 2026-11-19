<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReminderSchedule extends Model
{
    use HasFactory;
    use HasUlids;

    public const CHANNEL_EMAIL = 'email';

    public const WINDOW_T_MINUS_48H = 'T-48h';

    public const WINDOW_T_MINUS_24H = 'T-24h';

    public const WINDOW_T_MINUS_4H = 'T-4h';

    public const WINDOW_T_PLUS_24H = 'T+24h';

    public const STATUS_PENDING = 'pending';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'organization_id',
        'engagement_id',
        'metric_id',
        'form_assignment_id',
        'window',
        'channel',
        'deliver_at',
        'delivered_at',
        'status',
    ];

    protected $casts = [
        'deliver_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function engagement(): BelongsTo
    {
        return $this->belongsTo(Engagement::class);
    }

    public function metric(): BelongsTo
    {
        return $this->belongsTo(Metric::class);
    }

    public function formAssignment(): BelongsTo
    {
        return $this->belongsTo(FormAssignment::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(NotificationAttempt::class, 'notification_id');
    }

    /**
     * @return array<string,int>
     */
    public static function defaultWindowOffsets(): array
    {
        return [
            self::WINDOW_T_MINUS_48H => -48,
            self::WINDOW_T_MINUS_24H => -24,
            self::WINDOW_T_MINUS_4H => -4,
            self::WINDOW_T_PLUS_24H => 24,
        ];
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }
}
