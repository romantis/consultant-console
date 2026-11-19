<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class HandbookExport extends Model
{
    use HasFactory;
    use HasUlids;

    public const STATUS_QUEUED = 'queued';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_READY = 'ready';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'organization_id',
        'format',
        'storage_disk',
        'storage_path',
        'download_url',
        'expires_at',
        'checksum',
        'source_checksum',
        'status',
        'queued_at',
        'started_at',
        'completed_at',
        'failed_at',
        'failure_reason',
        'public_slug',
        'published_at',
        'published_by',
    ];

    protected $casts = [
        'source_checksum' => 'string',
        'expires_at' => 'datetime',
        'queued_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'failed_at' => 'datetime',
        'published_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $export) {
            if (! $export->public_slug) {
                $export->public_slug = static::generateUniquePublicSlug();
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function markProcessing(): void
    {
        $this->forceFill([
            'status' => self::STATUS_PROCESSING,
            'started_at' => now(),
        ])->save();
    }

    public function markReady(string $storagePath, ?string $downloadUrl = null, ?string $checksum = null, ?int $expiryMinutes = null): void
    {
        $disk = $this->storageDisk();

        if (! $downloadUrl && $disk) {
            $storage = Storage::disk($disk);

            try {
                if (method_exists($storage, 'temporaryUrl')) {
                    $downloadUrl = $storage->temporaryUrl(
                        $storagePath,
                        now()->addMinutes($expiryMinutes ?? (int) config('metric-harmony.handbook_url_expires_minutes', 15))
                    );
                }
            } catch (\Throwable $e) {
                // fall through to url() below
            }

            if (! $downloadUrl && method_exists($storage, 'url')) {
                $downloadUrl = $storage->url($storagePath);
            }
        }

        $this->forceFill([
            'status' => self::STATUS_READY,
            'storage_path' => $storagePath,
            'download_url' => $downloadUrl,
            'checksum' => $checksum,
            'completed_at' => now(),
        ])->save();
    }

    public function markFailed(?string $reason = null): void
    {
        $this->forceFill([
            'status' => self::STATUS_FAILED,
            'failure_reason' => $reason,
            'failed_at' => now(),
        ])->save();
    }

    public function publish(?User $user = null): void
    {
        $this->ensurePublicSlug();

        $this->forceFill([
            'published_at' => now(),
            'published_by' => $user?->getKey(),
        ])->save();
    }

    public function unpublish(): void
    {
        $this->forceFill([
            'published_at' => null,
            'published_by' => null,
        ])->save();
    }

    public function isPublished(): bool
    {
        return (bool) $this->published_at;
    }

    public function isReady(): bool
    {
        return $this->status === self::STATUS_READY && $this->storage_path;
    }

    public function storageDisk(): ?string
    {
        return $this->storage_disk ?: config('metric-harmony.handbook_disk');
    }

    public function temporaryUrl(?int $expiryMinutes = null): ?string
    {
        $disk = $this->storageDisk();
        $path = $this->storage_path;

        if (! $disk || ! $path) {
            return null;
        }

        $storage = Storage::disk($disk);

        try {
            if (method_exists($storage, 'temporaryUrl')) {
                return $storage->temporaryUrl(
                    $path,
                    Carbon::now()->addMinutes($expiryMinutes ?? (int) config('metric-harmony.handbook_url_expires_minutes', 15))
                );
            }
        } catch (\Throwable $e) {
        }

        if (method_exists($storage, 'url')) {
            return $storage->url($path);
        }

        return $this->download_url;
    }

    public function ensurePublicSlug(bool $persist = true): void
    {
        if ($this->public_slug) {
            return;
        }

        $this->public_slug = static::generateUniquePublicSlug();

        if ($persist && $this->exists) {
            $this->saveQuietly();
        }
    }

    protected static function generateUniquePublicSlug(): string
    {
        do {
            $slug = Str::lower((string) Str::ulid());
        } while (
            static::query()
                ->where('public_slug', $slug)
                ->exists()
        );

        return $slug;
    }
}
