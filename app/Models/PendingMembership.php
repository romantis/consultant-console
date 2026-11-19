<?php

namespace App\Models;

use App\Enums\MembershipRole;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PendingMembership extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'organization_id',
        'engagement_id',
        'email',
        'name',
        'role',
        'invited_by',
        'affiliation',
    ];

    protected $casts = [
        'role' => MembershipRole::class,
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function engagement()
    {
        return $this->belongsTo(Engagement::class);
    }

    public function inviter()
    {
        return $this->belongsTo(User::class, 'invited_by');
    }
}
