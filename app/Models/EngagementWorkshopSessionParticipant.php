<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EngagementWorkshopSessionParticipant extends Model
{
    use HasFactory;
    use HasUlids;

    protected $fillable = [
        'session_id',
        'engagement_participant_id',
    ];

    public function engagementParticipant(): BelongsTo
    {
        return $this->belongsTo(EngagementParticipant::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(EngagementWorkshopSession::class, 'session_id');
    }
}
