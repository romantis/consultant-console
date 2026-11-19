<?php

use App\Http\Controllers\Client\EngagementWorkshopController;
use App\Http\Controllers\Client\FormsController;
use App\Http\Controllers\Client\HandbookController;
use App\Http\Controllers\Client\MetricDetailController;
use App\Http\Controllers\Client\MetricOwnerReassignController;
use App\Http\Controllers\Client\OrgMetricController;
use App\Http\Controllers\EngagementHandbookController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'client'])
    ->prefix('client')
    ->group(function () {
        Route::name('client.')->group(function () {
            Route::get('/forms', FormsController::class)->name('forms.index');
            Route::get('/handbook', HandbookController::class)->name('handbook.index');
            Route::post('/organizations/{organization}/handbook/exports', [EngagementHandbookController::class, 'store'])
                ->name('handbook.exports.store');
            Route::get('/organizations/{organization}/engagements/{engagement}/workshop', EngagementWorkshopController::class)
                ->name('handbook.workshop');
            Route::get('/organizations/{organization}/engagements/{engagement}/metrics/{orgMetric}', OrgMetricController::class)
                ->name('handbook.metrics.page');
        });

        Route::prefix('api')->name('client.')->group(function () {
            Route::get('/organizations/{organization}/engagements/{engagement}/metrics/{metric}', MetricDetailController::class)
                ->name('handbook.metrics.show');
            Route::patch('/organizations/{organization}/engagements/{engagement}/metrics/{metric}/owner', MetricOwnerReassignController::class)
                ->middleware('feature.enabled:org_metric_overrides')
                ->name('handbook.metrics.owner.update');
        });
    });
