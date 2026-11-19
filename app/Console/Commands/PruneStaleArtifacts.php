<?php

namespace App\Console\Commands;

use App\Jobs\PruneArtifacts;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;

class PruneStaleArtifacts extends Command
{
    protected $signature = 'ops:prune-artifacts
        {--only= : Comma-separated list of artifacts to prune (notifications,handbook)}
        {--notifications-retention= : Override retention days for notification attempts}
        {--handbook-retention= : Override retention days for handbook exports}
        {--queue : Dispatch the pruning job to the queue instead of running synchronously}';

    protected $description = 'Prune notification attempts and handbook exports using the configured retention windows.';

    public function handle(): int
    {
        $segments = $this->parseSegments();
        $notificationDays = $this->parseRetentionOption('notifications-retention');
        $handbookDays = $this->parseRetentionOption('handbook-retention');

        $job = new PruneArtifacts($segments, $notificationDays, $handbookDays);

        if ($this->option('queue')) {
            Bus::dispatch($job);
            $this->info('Prune artifacts job dispatched.');
        } else {
            Bus::dispatchSync($job);
            $this->info('Prune artifacts job executed synchronously.');
        }

        $this->line(sprintf(
            'Segments: %s | Notification retention: %s days | Handbook retention: %s days',
            implode(',', $segments),
            $notificationDays !== null ? $notificationDays : config('metric-harmony.retention.notification_attempts_days', 30),
            $handbookDays !== null ? $handbookDays : config('metric-harmony.retention.handbook_exports_days', 60),
        ));

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    protected function parseSegments(): array
    {
        $only = $this->option('only');
        $allowed = ['notifications', 'handbook'];

        if (! $only) {
            return $allowed;
        }

        $segments = array_values(array_unique(array_filter(array_map(
            static fn ($segment) => in_array($segment, $allowed, true) ? $segment : null,
            array_map('trim', explode(',', (string) $only))
        ))));

        if (empty($segments)) {
            $this->warn('No valid artifacts specified for --only; defaulting to notifications,handbook.');

            return $allowed;
        }

        return $segments;
    }

    protected function parseRetentionOption(string $option): ?int
    {
        $value = $this->option($option);

        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            $this->warn("--{$option} must be a positive integer. Ignoring override.");

            return null;
        }

        $int = (int) $value;

        if ($int <= 0) {
            $this->warn("--{$option} must be greater than zero. Ignoring override.");

            return null;
        }

        return $int;
    }
}
