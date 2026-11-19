<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MetricDefinition extends Model
{
    use HasFactory;
    use HasUlids;

    protected $fillable = [
        'metric_id',
        'form_submission_id',
        'user_id',
        'source_type',
        'status',
        'definition_json',
        'normalized_json',
        'submitted_at',
    ];

    protected $casts = [
        'definition_json' => 'array',
        'normalized_json' => 'array',
        'submitted_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saved(function (self $definition) {
            if ($definition->status === 'Submitted') {
                $definition->metric?->alignmentDraft()?->delete();
            }
        });

        static::deleted(function (self $definition) {
            $definition->metric?->alignmentDraft()?->delete();
        });
    }

    public function metric(): BelongsTo
    {
        return $this->belongsTo(Metric::class);
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(FormSubmission::class, 'form_submission_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function normalizedField(string $key): ?array
    {
        $fields = $this->normalizedFields();

        if (array_key_exists($key, $fields)) {
            return $fields[$key];
        }

        $metaFields = $this->metaFields();

        return $metaFields[$key] ?? null;
    }

    public function normalizedFields(): array
    {
        $normalized = $this->normalized_json ?? [];

        if (isset($normalized['fields']) || isset($normalized['meta'])) {
            return $normalized['fields'] ?? [];
        }

        return is_array($normalized) ? $normalized : [];
    }

    public function metaFields(): array
    {
        $normalized = $this->normalized_json ?? [];

        if (isset($normalized['fields']) || isset($normalized['meta'])) {
            return $normalized['meta'] ?? [];
        }

        return [];
    }
}
