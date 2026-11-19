<?php

use App\Enums\EngagementStatus;
use App\Enums\FormAssignmentStatus;
use App\Enums\MetricStatus;
use App\Models\Engagement;
use App\Models\Metric;
use App\Models\Organization;
use App\Support\StateGuards\FormAssignmentStateGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function metricForAssignments(MetricStatus $status): Metric
{
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
        'status' => EngagementStatus::AWAITING_FORMS->value,
    ]);

    return Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'status' => $status->value,
    ]);
}

test('completed assignment cannot reopen once metric is finalized', function () {
    $metric = metricForAssignments(MetricStatus::FINALIZED);

    expect(fn () => FormAssignmentStateGuard::ensureValid(
        FormAssignmentStatus::COMPLETED,
        FormAssignmentStatus::REOPENED,
        $metric
    ))->toThrow(ValidationException::class);
});

test('completed assignment can reopen when metric is still in review', function () {
    $metric = metricForAssignments(MetricStatus::IN_REVIEW);

    expect(fn () => FormAssignmentStateGuard::ensureValid(
        FormAssignmentStatus::COMPLETED,
        FormAssignmentStatus::REOPENED,
        $metric
    ))->not->toThrow(ValidationException::class);
});

test('assignment cannot jump directly from open to reopened', function () {
    expect(fn () => FormAssignmentStateGuard::ensureValid(
        FormAssignmentStatus::OPEN,
        FormAssignmentStatus::REOPENED
    ))->toThrow(ValidationException::class);
});
