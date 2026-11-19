<?php

namespace App\Models;

use App\Enums\MembershipRole;
use App\Enums\StakeholderRole;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EngagementParticipant extends Model
{
    use HasFactory, HasUlids;

    public const TYPE_MEMBERSHIP = 'membership';

    public const TYPE_PENDING = 'pending_membership';

    protected $fillable = [
        'engagement_id',
        'participant_type',
        'membership_id',
        'pending_membership_id',
        'membership_role',
        'stakeholder_role',
        'active',
    ];

    protected $casts = [
        'membership_role' => MembershipRole::class,
        'stakeholder_role' => StakeholderRole::class,
        'active' => 'boolean',
    ];

    public function engagement(): BelongsTo
    {
        return $this->belongsTo(Engagement::class);
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class);
    }

    public function pendingMembership(): BelongsTo
    {
        return $this->belongsTo(PendingMembership::class);
    }
}
