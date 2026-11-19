<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrgFinalDefinition extends Model
{
    use HasFactory;
    use HasUlids;

    protected $fillable = [
        'org_metric_id',
        'source_metric_id',
        'source_metric_template_id',
        'status',
        'version',
        'definition_json',
        'notes',
        'operational_status',
        'operational_status_notes',
        'alignment_summary',
        'implementation_gaps',
        'validation_steps',
        'common_mismatch_causes',
        'executive_takeaway',
        'owner_participant_id',
        'signed_off_at',
        'signed_off_by',
    ];

    protected $casts = [
        'definition_json' => 'array',
        'implementation_gaps' => 'array',
        'signed_off_at' => 'datetime',
        'version' => 'integer',
    ];

    public function orgMetric(): BelongsTo
    {
        return $this->belongsTo(OrgMetric::class);
    }

    public function metric(): BelongsTo
    {
        return $this->belongsTo(Metric::class, 'source_metric_id');
    }

    public function metricTemplate(): BelongsTo
    {
        return $this->belongsTo(MetricTemplate::class, 'source_metric_template_id');
    }

    public function signedOffBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signed_off_by');
    }

    public function ownerParticipant(): BelongsTo
    {
        return $this->belongsTo(OrgMetricParticipant::class, 'owner_participant_id');
    }
}
