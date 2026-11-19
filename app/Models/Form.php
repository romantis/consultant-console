<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Form extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'organization_id',
        'engagement_id',
        'metric_id',
        'template_key',
        'title',
        'schema_json',
        'status',
        'last_submission_at',
    ];

    protected $casts = [
        'schema_json' => 'array',
        'last_submission_at' => 'datetime',
    ];

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<Engagement, $this> */
    public function engagement(): BelongsTo
    {
        return $this->belongsTo(Engagement::class);
    }

    /** @return BelongsTo<Metric, $this> */
    public function metric(): BelongsTo
    {
        return $this->belongsTo(Metric::class);
    }

    public function assignments()
    {
        return $this->hasMany(FormAssignment::class);
    }

    public function submissions()
    {
        return $this->hasMany(FormSubmission::class);
    }
}
