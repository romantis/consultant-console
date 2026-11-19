<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MetricFacilitationBrief extends Model
{
    use HasFactory;
    use HasUlids;

    protected $fillable = [
        'metric_id',
        'schema_version',
        'status',
        'source_fingerprint',
        'generated_draft',
        'document',
        'revision',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'generated_draft' => 'array',
        'document' => 'array',
        'revision' => 'integer',
    ];

    /**
     * @return BelongsTo<Metric, $this>
     */
    public function metric(): BelongsTo
    {
        return $this->belongsTo(Metric::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
