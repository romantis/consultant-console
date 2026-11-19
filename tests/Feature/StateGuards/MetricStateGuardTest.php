<?php

use App\Enums\EngagementStatus;
use App\Enums\MetricStatus;
use App\Models\Engagement;
use App\Models\Metric;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function createMetric(Organization $organization, Engagement $engagement, MetricStatus $status): Metric
{
    return Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'status' => $status->value,
    ]);
}

beforeEach(function () {
    $this->organization = Organization::factory()->create();
    $this->engagement = Engagement::factory()->create([
        'organization_id' => $this->organization->id,
        'status' => EngagementStatus::AWAITING_FORMS->value,
    ]);
});

test('metric cannot regress once review begins', function () {
    $metric = createMetric($this->organization, $this->engagement, MetricStatus::IN_REVIEW);

    expect(fn () => $metric->forceFill(['status' => MetricStatus::AWAITING_FORMS->value])->save())
        ->toThrow(ValidationException::class);

    $metric->refresh();

    expect($metric->status)->toBe(MetricStatus::IN_REVIEW);
});

test('finalized metric cannot revert to review', function () {
    $metric = createMetric($this->organization, $this->engagement, MetricStatus::FINALIZED);

    expect(fn () => $metric->forceFill(['status' => MetricStatus::IN_REVIEW->value])->save())
        ->toThrow(ValidationException::class);

    $metric->refresh();

    expect($metric->status)->toBe(MetricStatus::FINALIZED);
});
