<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    use HasFactory;
    use HasUlids;

    public const ACTION_IMPERSONATION_STARTED = 'impersonation.started';

    public const ACTION_IMPERSONATION_STOPPED = 'impersonation.stopped';

    public const ACTION_IMPERSONATION_ROLE_CHANGED = 'impersonation.role-changed';

    public const ACTION_IMPERSONATION_DENIED = 'impersonation.denied';

    public const ACTION_STAFF_OPERATOR_ASSIGNMENT_GRANTED = 'staff_operator_assignment.granted';

    public const ACTION_STAFF_OPERATOR_ASSIGNMENT_REVOKED = 'staff_operator_assignment.revoked';

    public const RESULT_STARTED = 'started';

    public const RESULT_STOPPED = 'stopped';

    public const RESULT_DENIED = 'denied';

    public const RESULT_ROLE_CHANGED = 'role-changed';

    protected $fillable = [
        'organization_id',
        'user_id',
        'action',
        'subject_type',
        'subject_id',
        'properties',
    ];

    protected $casts = [
        'properties' => 'array',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo(null, 'subject_type', 'subject_id');
    }

    public function getMetricIdAttribute(): ?string
    {
        return $this->properties['metric_id'] ?? null;
    }

    public function getEngagementIdAttribute(): ?string
    {
        return $this->properties['engagement_id'] ?? null;
    }

    public function getJustificationAttribute(): ?string
    {
        $value = $this->properties['justification'] ?? null;

        if (! is_scalar($value) && $value !== null) {
            return null;
        }

        $stringValue = trim((string) $value);

        return $stringValue === '' ? null : $stringValue;
    }

    public function getImpersonatedRoleAttribute(): ?string
    {
        $value = $this->properties['impersonated_role'] ?? null;

        if (! is_scalar($value) && $value !== null) {
            return null;
        }

        $stringValue = trim((string) $value);

        return $stringValue === '' ? null : $stringValue;
    }

    public function getResultAttribute(): ?string
    {
        $value = $this->properties['result'] ?? null;

        if (! is_scalar($value) && $value !== null) {
            return null;
        }

        $stringValue = trim((string) $value);

        return $stringValue === '' ? null : $stringValue;
    }
}
