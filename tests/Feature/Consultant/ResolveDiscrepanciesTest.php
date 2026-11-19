<?php

use App\Actions\Metrics\ComputeDiscrepancies;
use App\Enums\MembershipRole;
use App\Enums\MetricStatus;
use App\Models\Discrepancy;
use App\Models\Engagement;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\Membership;
use App\Models\Metric;
use App\Models\MetricDefinition;
use App\Models\Organization;
use App\Models\StaffOperatorAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;

uses(RefreshDatabase::class);

it('queues a discrepancy recompute and produces severity-ranked discrepancies', function () {
    Bus::fake();

    Carbon::setTestNow('2025-02-10 09:30:00');

    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
    ]);

    /** @var Metric $metric */
    $metric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'status' => MetricStatus::IN_REVIEW,
        'last_submitted_at' => null,
    ]);

    /** @var User $adminUser */
    $adminUser = User::factory()->create();

    Membership::factory()->admin()->create([
        'organization_id' => $organization->id,
        'user_id' => $adminUser->id,
    ]);

    $schema = [
        'fields' => [
            ['name' => 'total_mrr', 'type' => 'number', 'label' => 'Total MRR'],
            ['name' => 'notes', 'type' => 'text', 'label' => 'Notes'],
        ],
    ];

    /** @var Form $form */
    $form = Form::factory()
        ->for($engagement)
        ->create([
            'organization_id' => $organization->id,
            'metric_id' => $metric->id,
            'schema_json' => $schema,
        ]);

    /** @var User $analystA */
    $analystA = User::factory()->create(['name' => 'Analyst A']);
    /** @var User $analystB */
    $analystB = User::factory()->create(['name' => 'Analyst B']);

    /** @var FormSubmission $submissionA */
    $submissionA = FormSubmission::factory()->submitted()->create([
        'form_id' => $form->id,
        'user_id' => $analystA->id,
        'answers_json' => [
            'total_mrr' => 100000,
            'notes' => 'Baseline pull',
        ],
        'submitted_at' => now()->subDay(),
    ]);

    /** @var FormSubmission $submissionB */
    $submissionB = FormSubmission::factory()->submitted()->create([
        'form_id' => $form->id,
        'user_id' => $analystB->id,
        'answers_json' => [
            'total_mrr' => 140000,
            'notes' => 'Includes upsell',
        ],
        'submitted_at' => now(),
    ]);

    MetricDefinition::factory()->create([
        'metric_id' => $metric->id,
        'form_submission_id' => $submissionA->id,
        'user_id' => $analystA->id,
        'definition_json' => $submissionA->answers_json,
        'normalized_json' => [
            'fields' => [
                'total_mrr' => [
                    'type' => 'number',
                    'label' => 'Total MRR',
                    'raw' => 100000,
                    'normalized' => 100000.0,
                ],
                'notes' => [
                    'type' => 'text',
                    'label' => 'Notes',
                    'raw' => 'Baseline pull',
                    'normalized' => 'baseline pull',
                ],
            ],
            'meta' => [],
        ],
        'submitted_at' => $submissionA->submitted_at,
    ]);

    MetricDefinition::factory()->create([
        'metric_id' => $metric->id,
        'form_submission_id' => $submissionB->id,
        'user_id' => $analystB->id,
        'definition_json' => $submissionB->answers_json,
        'normalized_json' => [
            'fields' => [
                'total_mrr' => [
                    'type' => 'number',
                    'label' => 'Total MRR',
                    'raw' => 140000,
                    'normalized' => 140000.0,
                ],
                'notes' => [
                    'type' => 'text',
                    'label' => 'Notes',
                    'raw' => 'Includes upsell',
                    'normalized' => 'includes upsell',
                ],
            ],
            'meta' => [],
        ],
        'submitted_at' => $submissionB->submitted_at,
    ]);

    expect(Discrepancy::query()->count())->toBe(0);

    $response = $this->actingAs($adminUser)
        ->postJson("/api/metrics/{$metric->getKey()}/discrepancies/recompute")
        ->assertStatus(202)
        ->assertJsonPath('status', 'queued')
        ->assertJsonPath('queued_at', now()->toIso8601String())
        ->assertJsonPath('finished_at', null);

    $metric->refresh();

    expect($metric->recompute_status)->toBe('queued');
    expect(optional($metric->recompute_queued_at)->toIso8601String())->toBe(now()->toIso8601String());
    expect($metric->recompute_finished_at)->toBeNull();

    Bus::assertDispatched(ComputeDiscrepancies::class, function ($job) use ($metric, $adminUser) {
        $metricId = null;

        if (property_exists($job, 'metricId')) {
            $metricId = $job->metricId;
        } elseif (property_exists($job, 'metric')) {
            $metricProperty = $job->metric;
            $metricId = $metricProperty instanceof Metric
                ? (string) $metricProperty->getKey()
                : (string) $metricProperty;
        }

        if ($metricId === null) {
            return false;
        }

        $actorMatches = true;

        if (property_exists($job, 'actorId')) {
            $actorMatches = $job->actorId === (string) $adminUser->id;
        } elseif (property_exists($job, 'userId')) {
            $actorMatches = $job->userId === (string) $adminUser->id;
        }

        return (string) $metricId === (string) $metric->id && $actorMatches;
    });

    $dispatched = Bus::dispatched(ComputeDiscrepancies::class)->first();

    expect($dispatched)->not->toBeNull();

    $jobInstance = is_array($dispatched)
        ? ($dispatched['job'] ?? null)
        : $dispatched;

    expect($jobInstance)->toBeInstanceOf(ComputeDiscrepancies::class);

    app()->call([$jobInstance, 'handle']);

    $metric->refresh();
    expect($metric->recompute_status)->toBe('idle');
    expect($metric->recompute_finished_at)->not->toBeNull();

    $discrepancies = $metric->discrepancies()->get();

    expect($discrepancies)->toHaveCount(2);

    /** @var Discrepancy|null $numericDiscrepancy */
    $numericDiscrepancy = $discrepancies->firstWhere('field_key', 'total_mrr');
    expect($numericDiscrepancy)->not->toBeNull();
    expect($numericDiscrepancy->severity)->toBe('high');
    expect($numericDiscrepancy->status)->toBe('Open');
    expect(optional($numericDiscrepancy->detected_at)->toIso8601String())->toBe(now()->toIso8601String());

    $snapshot = $numericDiscrepancy->values_snapshot ?? [];

    expect($snapshot)->toBeArray();
    expect($snapshot['label'] ?? null)->toBe('Total MRR');
    expect(collect($snapshot['values'] ?? [])->pluck('normalized')->sort()->values()->all())
        ->toEqual([100000.0, 140000.0]);

    /** @var Discrepancy|null $notesDiscrepancy */
    $notesDiscrepancy = $discrepancies->firstWhere('field_key', 'notes');
    expect($notesDiscrepancy)->not->toBeNull();

    $notesAnalysis = $notesDiscrepancy->values_snapshot['analysis'] ?? [];
    expect($notesAnalysis)->not->toBeEmpty();
    expect($notesAnalysis['vector_used'])->toBeFalse();

    expect($metric->status)->toBe(MetricStatus::IN_REVIEW);

    Carbon::setTestNow();
});

it('skips recompute attempts when no definitions have changed', function () {
    Bus::fake();

    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
    ]);

    /** @var Metric $metric */
    $metric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'recompute_status' => 'idle',
        'recompute_finished_at' => now(),
        'definitions_changed_at' => now()->subMinute(),
    ]);

    $adminMembership = Membership::factory()->admin()->create([
        'organization_id' => $organization->id,
    ]);

    $this->actingAs($adminMembership->user)
        ->postJson("/api/metrics/{$metric->getKey()}/discrepancies/recompute")
        ->assertStatus(200)
        ->assertJsonPath('skipped', true)
        ->assertJsonPath('reason', 'up_to_date');

    Bus::assertNothingDispatched();
});

it('allows forcing a recompute even when no definitions changed', function () {
    Bus::fake();

    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
    ]);

    /** @var Metric $metric */
    $metric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'recompute_status' => 'idle',
        'recompute_finished_at' => now(),
        'definitions_changed_at' => now()->subMinute(),
    ]);

    $adminMembership = Membership::factory()->admin()->create([
        'organization_id' => $organization->id,
    ]);

    $this->actingAs($adminMembership->user)
        ->postJson("/api/metrics/{$metric->getKey()}/discrepancies/recompute?force=1")
        ->assertStatus(202)
        ->assertJsonPath('status', 'queued');

    Bus::assertDispatched(ComputeDiscrepancies::class, fn ($job) => $job->metricId === (string) $metric->id);
});

it('falls back to token similarity when pgvector scoring is unavailable', function () {
    Carbon::setTestNow('2025-02-11 08:00:00');

    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
    ]);

    /** @var Metric $metric */
    $metric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'status' => MetricStatus::IN_REVIEW,
    ]);

    $schema = [
        'fields' => [
            ['name' => 'narrative', 'type' => 'text', 'label' => 'Narrative'],
        ],
    ];

    /** @var Form $form */
    $form = Form::factory()
        ->for($engagement)
        ->create([
            'organization_id' => $organization->id,
            'metric_id' => $metric->id,
            'schema_json' => $schema,
        ]);

    [$writerA, $writerB] = User::factory()->count(2)->create();

    $submissionA = FormSubmission::factory()->submitted()->create([
        'form_id' => $form->id,
        'user_id' => $writerA->id,
        'answers_json' => [
            'narrative' => 'Legacy data center runbook and escalation paths',
        ],
    ]);

    $submissionB = FormSubmission::factory()->submitted()->create([
        'form_id' => $form->id,
        'user_id' => $writerB->id,
        'answers_json' => [
            'narrative' => 'Current cloud-first reporting excludes data center stories entirely',
        ],
    ]);

    MetricDefinition::factory()->create([
        'metric_id' => $metric->id,
        'form_submission_id' => $submissionA->id,
        'user_id' => $writerA->id,
        'status' => 'Submitted',
        'definition_json' => $submissionA->answers_json,
        'normalized_json' => [
            'fields' => [
                'narrative' => [
                    'type' => 'text',
                    'label' => 'Narrative',
                    'raw' => 'Legacy data center runbook and escalation paths',
                    'normalized' => 'legacy data center runbook and escalation paths',
                ],
            ],
            'meta' => [],
        ],
        'submitted_at' => $submissionA->submitted_at,
    ]);

    MetricDefinition::factory()->create([
        'metric_id' => $metric->id,
        'form_submission_id' => $submissionB->id,
        'user_id' => $writerB->id,
        'status' => 'Submitted',
        'definition_json' => $submissionB->answers_json,
        'normalized_json' => [
            'fields' => [
                'narrative' => [
                    'type' => 'text',
                    'label' => 'Narrative',
                    'raw' => 'Current cloud-first reporting excludes data center stories entirely',
                    'normalized' => 'current cloud first reporting excludes data center stories entirely',
                ],
            ],
            'meta' => [],
        ],
        'submitted_at' => $submissionB->submitted_at,
    ]);

    $job = new ComputeDiscrepancies((string) $metric->id);
    $job->handle();

    $metric->refresh();
    $discrepancy = $metric->discrepancies()->first();

    expect($metric->discrepancies()->count())->toBe(1);
    expect($discrepancy->field_key)->toBe('narrative');

    $analysis = $discrepancy->values_snapshot['analysis'] ?? [];
    expect($analysis)->not->toBeEmpty();
    expect($analysis['vector_used'])->toBeFalse();
    expect($analysis['score'])->toBeGreaterThan(20);

    Carbon::setTestNow();
});

it('returns a discrepancy feed with filters and ability metadata', function () {
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
    ]);

    /** @var Metric $metric */
    $metric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
    ]);

    $adminMembership = Membership::factory()->admin()->create([
        'organization_id' => $organization->id,
    ]);

    Discrepancy::factory()->create([
        'engagement_id' => $engagement->id,
        'metric_id' => $metric->id,
        'field_key' => 'total_mrr',
        'severity' => 'high',
        'status' => 'Open',
        'detected_at' => now()->subDay(),
    ]);

    Discrepancy::factory()->create([
        'engagement_id' => $engagement->id,
        'metric_id' => $metric->id,
        'field_key' => 'notes',
        'severity' => 'medium',
        'status' => 'Open',
        'detected_at' => now()->subHours(12),
    ]);

    Discrepancy::factory()->create([
        'engagement_id' => $engagement->id,
        'metric_id' => $metric->id,
        'field_key' => 'owner',
        'severity' => 'low',
        'status' => 'Resolved',
        'resolved_at' => now()->subHours(2),
    ]);

    $response = $this->actingAs($adminMembership->user)
        ->getJson("/api/metrics/{$metric->getKey()}/discrepancies?status=open&field[]=total_mrr")
        ->assertOk();

    $response->assertJsonPath('meta.counts.open', 2);
    $response->assertJsonPath('meta.counts.resolved', 1);
    $response->assertJsonPath('meta.counts.total', 3);
    $response->assertJsonPath('meta.filters.status', 'open');
    $response->assertJsonPath('meta.filters.field.0', 'total_mrr');
    $response->assertJsonPath('meta.permissions.resolve', false);
    $response->assertJsonPath('meta.recompute.status', 'idle');
    $response->assertJsonPath('meta.severity.high', 1);
    $response->assertJsonCount(1, 'data');
    $response->assertJsonPath('data.0.field_key', 'total_mrr');
});

it('allows staff users to update discrepancy status and forbids non-staff', function () {
    $organization = Organization::factory()->create(['is_internal' => false]);
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
    ]);

    $metric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
    ]);

    $adminMembership = Membership::factory()->admin()->create([
        'organization_id' => $organization->id,
    ]);

    $staffUser = User::factory()->create();
    $internalOrg = Organization::factory()->create(['is_internal' => true]);
    Membership::factory()->admin()->create([
        'organization_id' => $internalOrg->id,
        'user_id' => $staffUser->id,
    ]);

    $discrepancy = Discrepancy::factory()->create([
        'engagement_id' => $engagement->id,
        'metric_id' => $metric->id,
        'field_key' => 'total_mrr',
        'status' => 'Open',
        'values_snapshot' => [
            'label' => 'Total MRR',
            'values' => [],
        ],
        'resolved_at' => null,
    ]);

    $this->actingAs($adminMembership->user)
        ->patchJson("/api/metrics/{$metric->getKey()}/discrepancies/{$discrepancy->getKey()}", [
            'status' => 'Resolved',
            'notes' => 'Discussed during workshop',
        ])
        ->assertForbidden();

    $this->actingAs($staffUser)
        ->patchJson("/api/metrics/{$metric->getKey()}/discrepancies/{$discrepancy->getKey()}", [
            'status' => 'Resolved',
            'notes' => 'Discussed during workshop',
        ])
        ->assertOk();

    $discrepancy->refresh();

    expect($discrepancy->status)->toBe('Resolved');
    expect($discrepancy->resolved_at)->not->toBeNull();
});

it('allows delegated operators to resolve discrepancies inside their assigned organization', function () {
    $organization = Organization::factory()->create(['is_internal' => false]);
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
    ]);

    $metric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
    ]);

    $operator = User::factory()->create();
    $internalOrg = Organization::factory()->create(['is_internal' => true]);
    Membership::factory()->create([
        'organization_id' => $internalOrg->id,
        'user_id' => $operator->id,
        'role' => MembershipRole::MEMBER,
    ]);

    StaffOperatorAssignment::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $operator->id,
    ]);

    $discrepancy = Discrepancy::factory()->create([
        'engagement_id' => $engagement->id,
        'metric_id' => $metric->id,
        'field_key' => 'total_mrr',
        'status' => 'Open',
        'values_snapshot' => [
            'label' => 'Total MRR',
            'values' => [],
        ],
        'resolved_at' => null,
    ]);

    $this->actingAs($operator)
        ->patchJson("/api/metrics/{$metric->getKey()}/discrepancies/{$discrepancy->getKey()}", [
            'status' => 'Resolved',
            'notes' => 'Discussed during workshop',
        ])
        ->assertOk();
});

it('denies delegated operators from resolving discrepancies outside their assigned organization', function () {
    $organization = Organization::factory()->create(['is_internal' => false]);
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
    ]);

    $metric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
    ]);

    $operator = User::factory()->create();
    $internalOrg = Organization::factory()->create(['is_internal' => true]);
    Membership::factory()->create([
        'organization_id' => $internalOrg->id,
        'user_id' => $operator->id,
        'role' => MembershipRole::MEMBER,
    ]);

    $otherOrg = Organization::factory()->create(['is_internal' => false]);
    StaffOperatorAssignment::factory()->create([
        'organization_id' => $otherOrg->id,
        'user_id' => $operator->id,
    ]);

    $discrepancy = Discrepancy::factory()->create([
        'engagement_id' => $engagement->id,
        'metric_id' => $metric->id,
        'field_key' => 'total_mrr',
        'status' => 'Open',
        'values_snapshot' => [
            'label' => 'Total MRR',
            'values' => [],
        ],
        'resolved_at' => null,
    ]);

    $this->actingAs($operator)
        ->patchJson("/api/metrics/{$metric->getKey()}/discrepancies/{$discrepancy->getKey()}", [
            'status' => 'Resolved',
            'notes' => 'Discussed during workshop',
        ])
        ->assertForbidden();
});
