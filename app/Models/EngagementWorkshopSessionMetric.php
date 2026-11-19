<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EngagementWorkshopSessionMetric extends Model
{
    use HasFactory;
    use HasUlids;

    protected $fillable = [
        'session_id',
        'metric_id',
    ];

    public function metric(): BelongsTo
    {
        return $this->belongsTo(Metric::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(EngagementWorkshopSession::class, 'session_id');
    }
}
