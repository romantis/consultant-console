<?php

use App\Enums\EngagementStatus;
use App\Jobs\ProcessAssignmentReminders;
use App\Models\Engagement;
use App\Models\Form;
use App\Models\Membership;
use App\Models\Metric;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

it('removes the legacy manual form creation route', function () {
    expect(Route::has('org.forms.store'))->toBeFalse();
});

it('requires forms to be linked to a metric and unique per metric', function () {
    $metric = Metric::factory()->create();

    $form = Form::create([
        'organization_id' => $metric->organization_id,
        'engagement_id' => $metric->engagement_id,
        'metric_id' => $metric->id,
        'template_key' => 'revenue-growth',
        'title' => 'Revenue Growth - Definition',
        'schema_json' => ['fields' => []],
        'status' => 'Open',
    ]);

    expect($form->exists)->toBeTrue();

    expect(function () use ($metric): void {
        Form::create([
            'organization_id' => $metric->organization_id,
            'engagement_id' => $metric->engagement_id,
            'metric_id' => null,
            'template_key' => 'missing-link',
            'title' => 'Missing Metric Link',
            'schema_json' => ['fields' => []],
            'status' => 'Open',
        ]);
    })->toThrow(QueryException::class);

    expect(function () use ($metric): void {
        Form::create([
            'organization_id' => $metric->organization_id,
            'engagement_id' => $metric->engagement_id,
            'metric_id' => $metric->id,
            'template_key' => 'duplicate-link',
            'title' => 'Duplicate Metric Link',
            'schema_json' => ['fields' => []],
            'status' => 'Open',
        ]);
    })->toThrow(QueryException::class);
});

it('blocks engagement status updates when engagement is finalized', function () {
    $user = User::factory()->create();
    $internal = makeInternalOrg();
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
        'status' => EngagementStatus::FINALIZED->value,
    ]);

    Membership::factory()->admin()->create([
        'organization_id' => $organization->id,
        'user_id' => $user->id,
    ]);
    Membership::factory()->admin()->create([
        'organization_id' => $internal->id,
        'user_id' => $user->id,
    ]);

    $response = $this->actingAs($user)->patchJson(
        route('org.engagements.status.update', [$organization, $engagement]),
        ['status' => EngagementStatus::IN_REVIEW->value]
    );

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['engagement'])
        ->assertJsonPath('errors.engagement.0', 'Engagement is finalized and read-only.');
});

it('blocks reminder pass trigger when engagement is finalized', function () {
    Queue::fake();

    $user = User::factory()->create();
    $internal = makeInternalOrg();
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
        'status' => EngagementStatus::FINALIZED->value,
    ]);

    Membership::factory()->admin()->create([
        'organization_id' => $organization->id,
        'user_id' => $user->id,
    ]);
    Membership::factory()->admin()->create([
        'organization_id' => $internal->id,
        'user_id' => $user->id,
    ]);

    $response = $this->actingAs($user)->postJson(
        route('org.engagements.reminders.store', [$organization, $engagement])
    );

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['engagement'])
        ->assertJsonPath('errors.engagement.0', 'Engagement is finalized and read-only.');

    Queue::assertNotPushed(ProcessAssignmentReminders::class);
});
