<?php

namespace App\Providers;

use App\Models\PendingMembership;
use App\Observers\PendingMembershipObserver;
use App\Support\Handbook\HandbookPdfGenerator;
use App\Support\Handbook\HandbookPdfRenderer;
use App\Support\Metrics\Drafting\LaravelAiMetricAlignmentDraftGateway;
use App\Support\Metrics\Drafting\MetricAlignmentDraftGateway;
use App\Support\Observability\Telemetry;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password as PasswordRule;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(HandbookPdfRenderer::class, HandbookPdfGenerator::class);
        $this->app->singleton(MetricAlignmentDraftGateway::class, LaravelAiMetricAlignmentDraftGateway::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        PendingMembership::observe(PendingMembershipObserver::class);

        PasswordRule::defaults(function () {
            $rule = PasswordRule::min(8);

            if (app()->environment('production')) {
                $rule = $rule
                    ->letters()
                    ->mixedCase()
                    ->numbers()
                    ->symbols()
                    ->uncompromised();
            }

            return $rule;
        });

        $this->registerSlowQueryLogging();
    }

    protected function registerSlowQueryLogging(): void
    {
        $threshold = (int) config('metric-harmony.observability.slow_query_threshold_ms', 0);

        if ($threshold <= 0) {
            return;
        }

        DB::listen(function (QueryExecuted $event) use ($threshold) {
            if ($event->time < $threshold) {
                return;
            }

            $payload = [
                'sql' => $event->sql,
                'connection' => $event->connectionName,
                'time_ms' => $event->time,
                'tags' => Telemetry::tags(),
            ];

            if (config('metric-harmony.observability.log_bindings', false)) {
                $payload['bindings'] = $event->bindings;
            }

            Log::warning('Slow query detected', $payload);
        });
    }
}
