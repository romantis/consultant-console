<?php

use App\Http\Middleware\EnsureClientContext;
use App\Http\Middleware\EnsureFeatureEnabled;
use App\Http\Middleware\EnsureOwner;
use App\Http\Middleware\EnsureRegistrationEnabled;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ScopeGuard;
use App\Http\Middleware\SetActiveOrganization;
use App\Http\Middleware\ThrottleAutosave;
use App\Jobs\ProcessAssignmentReminders;
use App\Jobs\ProcessWorkshopSessionReminders;
use App\Jobs\PruneArtifacts;
use App\Support\Impersonation;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: [
            __DIR__.'/../routes/web.php',
            __DIR__.'/../routes/client.php',
        ],
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->encryptCookies(
            except: [
                'appearance',
                'sidebar_state',
                Impersonation::ACTIVE_ORGANIZATION_COOKIE,
            ],
        );

        $middleware->alias([
            'registration.enabled' => EnsureRegistrationEnabled::class,
            'feature.enabled' => EnsureFeatureEnabled::class,
            'owner' => EnsureOwner::class,
            'autosave.throttle' => ThrottleAutosave::class,
            'client' => EnsureClientContext::class,
        ]);

        $middleware->web(append: [
            HandleAppearance::class,
            SetActiveOrganization::class,
            ScopeGuard::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withSchedule(function ($schedule) {
        $schedule->job(new ProcessAssignmentReminders)->everyFiveMinutes();
        $schedule->job(new ProcessWorkshopSessionReminders)->everyFiveMinutes();
        $schedule->job(new PruneArtifacts)
            ->dailyAt('03:30')
            ->onOneServer();
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
