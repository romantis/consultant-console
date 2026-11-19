<?php

namespace App\Models;

use App\Enums\ChangeProposalStatus;
use App\Support\StateGuards\ChangeProposalStateGuard;
use BackedEnum;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChangeProposal extends Model
{
    use HasFactory;
    use HasUlids;

    protected $fillable = [
        'organization_id',
        'engagement_id',
        'metric_id',
        'initiated_by',
        'status',
        'rationale',
        'payload',
        'approver_ids',
        'approved_at',
        'merged_at',
    ];

    protected $casts = [
        'status' => ChangeProposalStatus::class,
        'payload' => 'array',
        'approver_ids' => 'array',
        'approved_at' => 'datetime',
        'merged_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $proposal) {
            if (! $proposal->isDirty('status')) {
                return;
            }

            $original = $proposal->getOriginal('status');
            $from = match (true) {
                $original instanceof ChangeProposalStatus => $original,
                $original instanceof BackedEnum => ChangeProposalStatus::from($original->value),
                $original !== null => ChangeProposalStatus::from((string) $original),
                default => null,
            };
            $current = $proposal->status;
            $next = $current instanceof ChangeProposalStatus
                ? $current
                : ($current instanceof BackedEnum
                    ? ChangeProposalStatus::from($current->value)
                    : ChangeProposalStatus::from((string) $current));

            ChangeProposalStateGuard::ensureValid($from, $next);
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

    public function metric(): BelongsTo
    {
        return $this->belongsTo(Metric::class);
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(Membership::class, 'initiated_by');
    }
}
