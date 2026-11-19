<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MetricAlignmentDraft extends Model
{
    use HasFactory;
    use HasUlids;

    protected $fillable = [
        'metric_id',
        'profile_version',
        'prompt_version',
        'provider',
        'requested_model',
        'returned_model',
        'source_fingerprint',
        'draft',
        'usage',
    ];

    protected $casts = [
        'draft' => 'array',
        'usage' => 'array',
    ];

    /**
     * @return BelongsTo<Metric, $this>
     */
    public function metric(): BelongsTo
    {
        return $this->belongsTo(Metric::class);
    }
}
