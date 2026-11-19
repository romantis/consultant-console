<?php

namespace App\Models;

use App\Enums\EngagementStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Organization extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = ['name', 'slug', 'settings_json'];

    protected $casts = [
        'settings_json' => 'array',
        'is_internal' => 'boolean',
    ];

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    public function pendingMemberships(): HasMany
    {
        return $this->hasMany(PendingMembership::class);
    }

    /**
     * @return BelongsToMany<User, Organization>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'memberships')->withPivot('role');
    }

    public function engagements(): HasMany
    {
        return $this->hasMany(Engagement::class);
    }

    public function metrics(): HasMany
    {
        return $this->hasMany(Metric::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        $activeStatuses = array_map(
            static fn (EngagementStatus $status) => $status->value,
            EngagementStatus::activePhases()
        );

        return $query->whereHas('engagements', function (Builder $builder) use ($activeStatuses) {
            $builder->whereIn('status', $activeStatuses);
        });
    }

    public function scopeOnboarding(Builder $query): Builder
    {
        return $query->whereHas('engagements', function (Builder $builder) {
            $builder->where('status', EngagementStatus::DRAFT);
        });
    }

    public function scopeCompleted(Builder $query): Builder
    {
        $terminalStatuses = array_map(
            static fn (EngagementStatus $status) => $status->value,
            EngagementStatus::terminalPhases()
        );

        return $query->whereHas('engagements', function (Builder $builder) use ($terminalStatuses) {
            $builder->whereIn('status', $terminalStatuses);
        });
    }

    public function scopeIdle(Builder $query): Builder
    {
        $excluded = array_map(
            static fn (EngagementStatus $status) => $status->value,
            array_merge(EngagementStatus::activePhases(), [EngagementStatus::DRAFT])
        );

        return $query->whereDoesntHave('engagements', function (Builder $builder) use ($excluded) {
            $builder->whereIn('status', $excluded);
        });
    }
}
