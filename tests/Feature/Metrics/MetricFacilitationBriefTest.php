<?php

use App\Enums\MembershipRole;
use App\Models\Engagement;
use App\Models\Membership;
use App\Models\Metric;
use App\Models\MetricDefinition;
use App\Models\MetricFacilitationBrief;
use App\Models\MetricTemplate;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function facilitationStaffUser(): User
{
    $staff = User::factory()->create();
    $internal = Organization::factory()->create(['is_internal' => true]);

    Membership::factory()
        ->for($internal, 'organization')
        ->for($staff, 'user')
        ->admin()
        ->create();

    return $staff;
}

/**
 * A metric whose default template field ("value", required) yields a canonical
 * missing-evidence agenda item, so the facilitation plan is derived
 * deterministically from the brief — no AI draft involved.
 *
 * @return array{0: Organization, 1: Engagement, 2: Metric}
 */
function facilitationMetric(): array
{
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->create(['organization_id' => $organization->id]);
    $metric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
    ]);

    return [$organization, $engagement, $metric];
}

/**
 * A metric whose only field is optional, ungrounded, and consensus-bearing, so
 * the canonical brief carries no conflict, missing evidence, or decision — there
 * is nothing to prepare.
 *
 * @return array{0: Organization, 1: Engagement, 2: Metric}
 */
function facilitationMetricWithoutAgenda(): array
{
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->create(['organization_id' => $organization->id]);
    $template = MetricTemplate::factory()->create([
        'form_schema_json' => [
            'fields' => [[
                'name' => 'value',
                'type' => 'number',
                'label' => 'Metric Value',
                'required' => false,
                'classification' => ['role' => 'definition'],
            ]],
        ],
    ]);
    $metric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'metric_template_id' => $template->id,
    ]);

    return [$organization, $engagement, $metric];
}

it('derives and persists a facilitation plan from the canonical brief', function () {
    $staff = facilitationStaffUser();
    [$organization, $engagement, $metric] = facilitationMetric();

    $response = $this->actingAs($staff)
        ->postJson(route('org.engagements.metrics.prep.facilitation.store', [
            'organization' => $organization,
            'engagement' => $engagement,
            'metric' => $metric,
        ]))
        ->assertCreated();

    $response->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.revision', 1)
        ->assertJsonPath('data.needs_source_review', false)
        ->assertJsonPath('data.document.run_of_show.0.title', 'Frame the metric and alignment goal')
        ->assertJsonPath('data.document.run_of_show.0.purpose.provenance', 'system_suggested');

    $runOfShow = $response->json('data.document.run_of_show');
    expect($runOfShow)->toHaveCount(3);
    expect($response->json('data.document.expected_outputs'))->not->toBeEmpty();

    $this->assertDatabaseHas('metric_facilitation_briefs', [
        'metric_id' => $metric->id,
        'status' => 'draft',
        'revision' => 1,
        'created_by' => $staff->id,
    ]);
});

it('returns nothing-to-prepare when the metric has no canonical agenda', function () {
    $staff = facilitationStaffUser();
    [$organization, $engagement, $metric] = facilitationMetricWithoutAgenda();

    $this->actingAs($staff)
        ->postJson(route('org.engagements.metrics.prep.facilitation.store', [
            'organization' => $organization,
            'engagement' => $engagement,
            'metric' => $metric,
        ]))
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'nothing_to_prepare');

    $this->assertDatabaseCount('metric_facilitation_briefs', 0);
});

it('prepares a deterministic plan from the canonical agenda without a generated draft', function () {
    $staff = facilitationStaffUser();
    [$organization, $engagement, $metric] = facilitationMetric();

    $response = $this->actingAs($staff)
        ->postJson(route('org.engagements.metrics.prep.facilitation.store', [
            'organization' => $organization,
            'engagement' => $engagement,
            'metric' => $metric,
        ]))
        ->assertCreated();

    // A complete plan is produced with no AI draft; the snapshot records none.
    $response->assertJsonPath('data.document.source_snapshot.generated_draft', null);
    expect($response->json('data.document.run_of_show'))->not->toBeEmpty();
    expect($response->json('data.document.decisions_to_lock'))->not->toBeEmpty();

    $this->assertDatabaseCount('metric_facilitation_briefs', 1);
    $this->assertDatabaseMissing('metric_alignment_drafts', ['metric_id' => $metric->id]);
});

it('returns the existing plan instead of re-deriving', function () {
    $staff = facilitationStaffUser();
    [$organization, $engagement, $metric] = facilitationMetric();

    $args = [
        'organization' => $organization,
        'engagement' => $engagement,
        'metric' => $metric,
    ];

    $first = $this->actingAs($staff)
        ->postJson(route('org.engagements.metrics.prep.facilitation.store', $args))
        ->assertCreated();

    $second = $this->actingAs($staff)
        ->postJson(route('org.engagements.metrics.prep.facilitation.store', $args))
        ->assertOk();

    expect($second->json('data.id'))->toBe($first->json('data.id'));
    expect($second->json('data.revision'))->toBe(1);
    $this->assertDatabaseCount('metric_facilitation_briefs', 1);
});

it('updates the run of show with optimistic concurrency, no expected_outputs in the payload', function () {
    $staff = facilitationStaffUser();
    [$organization, $engagement, $metric] = facilitationMetric();

    $args = [
        'organization' => $organization,
        'engagement' => $engagement,
        'metric' => $metric,
    ];

    $created = $this->actingAs($staff)
        ->postJson(route('org.engagements.metrics.prep.facilitation.store', $args))
        ->assertCreated();

    $document = $created->json('data.document');
    $document['run_of_show'][0]['title'] = 'Kick off and align on goals';
    $document['run_of_show'][0]['duration_minutes'] = 12;
    $document['run_of_show'][0]['purpose']['text'] = 'Consultant-edited opening framing.';
    $document['run_of_show'][0]['purpose']['provenance'] = 'consultant_authored';

    // T060: expected_outputs is hidden and server-authoritative, so the client no
    // longer sends it — the save must still succeed and the persisted outputs are
    // returned untouched.
    $persistedOutputs = $document['expected_outputs'];

    $updated = $this->actingAs($staff)
        ->patchJson(route('org.engagements.metrics.prep.facilitation.update', $args), [
            'revision' => 1,
            'status' => 'ready_for_workshop',
            'run_of_show' => $document['run_of_show'],
        ])
        ->assertOk();

    $updated->assertJsonPath('data.revision', 2)
        ->assertJsonPath('data.status', 'ready_for_workshop')
        ->assertJsonPath('data.document.run_of_show.0.title', 'Kick off and align on goals')
        ->assertJsonPath('data.document.run_of_show.0.duration_minutes', 12)
        ->assertJsonPath('data.document.run_of_show.0.purpose.provenance', 'consultant_authored')
        ->assertJsonPath('data.document.expected_outputs', $persistedOutputs);

    $this->assertDatabaseHas('metric_facilitation_briefs', [
        'metric_id' => $metric->id,
        'revision' => 2,
        'status' => 'ready_for_workshop',
        'updated_by' => $staff->id,
    ]);
});

it('preserves persisted expected_outputs against a stale client copy', function () {
    $staff = facilitationStaffUser();
    [$organization, $engagement, $metric] = facilitationMetric();

    $args = [
        'organization' => $organization,
        'engagement' => $engagement,
        'metric' => $metric,
    ];

    $created = $this->actingAs($staff)
        ->postJson(route('org.engagements.metrics.prep.facilitation.store', $args))
        ->assertCreated();

    $document = $created->json('data.document');
    $staleOutputs = $document['expected_outputs'];

    // A concurrent process changes the stored expected_outputs after the page
    // loaded its now-stale copy.
    $brief = MetricFacilitationBrief::where('metric_id', $metric->id)->firstOrFail();
    $concurrentDocument = $brief->document;
    $concurrentDocument['expected_outputs'][0]['status'] = 'prepared';
    $brief->forceFill(['document' => $concurrentDocument])->save();

    // The page saves a run-of-show edit while still carrying the stale outputs.
    // The server ignores the client copy, so the concurrent change survives.
    $document['run_of_show'][0]['title'] = 'Kick off and align on goals';

    $this->actingAs($staff)
        ->patchJson(route('org.engagements.metrics.prep.facilitation.update', $args), [
            'revision' => 1,
            'status' => 'draft',
            'run_of_show' => $document['run_of_show'],
            'expected_outputs' => $staleOutputs,
        ])
        ->assertOk()
        ->assertJsonPath('data.document.run_of_show.0.title', 'Kick off and align on goals')
        ->assertJsonPath('data.document.expected_outputs.0.status', 'prepared');
});

it('never downgrades consultant-authored provenance back to system_suggested', function () {
    $staff = facilitationStaffUser();
    [$organization, $engagement, $metric] = facilitationMetric();

    $args = [
        'organization' => $organization,
        'engagement' => $engagement,
        'metric' => $metric,
    ];

    $created = $this->actingAs($staff)
        ->postJson(route('org.engagements.metrics.prep.facilitation.store', $args))
        ->assertCreated();

    $document = $created->json('data.document');
    $document['run_of_show'][0]['purpose']['text'] = 'Consultant-edited opening framing.';
    $document['run_of_show'][0]['purpose']['provenance'] = 'consultant_authored';

    $this->actingAs($staff)
        ->patchJson(route('org.engagements.metrics.prep.facilitation.update', $args), [
            'revision' => 1,
            'status' => 'draft',
            'run_of_show' => $document['run_of_show'],
            'expected_outputs' => $document['expected_outputs'],
        ])
        ->assertOk();

    // Resubmit the same text while claiming it is system_suggested again.
    $document['run_of_show'][0]['purpose']['provenance'] = 'system_suggested';

    $this->actingAs($staff)
        ->patchJson(route('org.engagements.metrics.prep.facilitation.update', $args), [
            'revision' => 2,
            'status' => 'draft',
            'run_of_show' => $document['run_of_show'],
            'expected_outputs' => $document['expected_outputs'],
        ])
        ->assertOk()
        ->assertJsonPath('data.document.run_of_show.0.purpose.provenance', 'consultant_authored');
});

it('rejects a stale revision when saving facilitation edits', function () {
    $staff = facilitationStaffUser();
    [$organization, $engagement, $metric] = facilitationMetric();

    $args = [
        'organization' => $organization,
        'engagement' => $engagement,
        'metric' => $metric,
    ];

    $created = $this->actingAs($staff)
        ->postJson(route('org.engagements.metrics.prep.facilitation.store', $args))
        ->assertCreated();

    $document = $created->json('data.document');

    $this->actingAs($staff)
        ->patchJson(route('org.engagements.metrics.prep.facilitation.update', $args), [
            'revision' => 99,
            'status' => 'draft',
            'run_of_show' => $document['run_of_show'],
            'expected_outputs' => $document['expected_outputs'],
        ])
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'stale_facilitation_revision');

    $this->assertDatabaseHas('metric_facilitation_briefs', [
        'metric_id' => $metric->id,
        'revision' => 1,
    ]);
});

it('rejects invalid facilitation edit payloads', function () {
    $staff = facilitationStaffUser();
    [$organization, $engagement, $metric] = facilitationMetric();

    $args = [
        'organization' => $organization,
        'engagement' => $engagement,
        'metric' => $metric,
    ];

    $created = $this->actingAs($staff)
        ->postJson(route('org.engagements.metrics.prep.facilitation.store', $args))
        ->assertCreated();

    $document = $created->json('data.document');
    $document['run_of_show'][0]['duration_minutes'] = 999;

    $this->actingAs($staff)
        ->patchJson(route('org.engagements.metrics.prep.facilitation.update', $args), [
            'revision' => 1,
            'status' => 'draft',
            'run_of_show' => $document['run_of_show'],
            'expected_outputs' => $document['expected_outputs'],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('run_of_show.0.duration_minutes');
});

it('flags the plan for source review when the brief fingerprint changes', function () {
    $staff = facilitationStaffUser();
    [$organization, $engagement, $metric] = facilitationMetric();

    $args = [
        'organization' => $organization,
        'engagement' => $engagement,
        'metric' => $metric,
    ];

    $this->actingAs($staff)
        ->postJson(route('org.engagements.metrics.prep.facilitation.store', $args))
        ->assertCreated();

    MetricFacilitationBrief::where('metric_id', $metric->id)
        ->update(['source_fingerprint' => 'stale-fingerprint']);

    $this->actingAs($staff)
        ->get(route('org.engagements.metrics.prep', $args))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('facilitation_brief.needs_source_review', true)
            ->where('facilitation_brief.status', 'needs_source_review')
        );
});

it('flags the plan for source review when a template readiness change opens a new gap', function () {
    $staff = facilitationStaffUser();
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->create(['organization_id' => $organization->id]);
    $template = MetricTemplate::factory()->create([
        'form_schema_json' => [
            'required_departments' => ['Finance', 'Marketing'],
            'fields' => [
                [
                    'name' => 'team_department',
                    'type' => 'text',
                    'label' => 'Respondent team or department',
                    'required' => true,
                    'meta' => true,
                    'classification' => ['role' => 'context', 'exclude_from_consensus' => true],
                ],
                [
                    'name' => 'value',
                    'type' => 'number',
                    'label' => 'Metric Value',
                    'required' => true,
                    'classification' => ['role' => 'definition'],
                ],
            ],
        ],
    ]);
    $metric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'metric_template_id' => $template->id,
    ]);
    MetricDefinition::factory()->create([
        'metric_id' => $metric->id,
        'definition_json' => ['team_department' => 'Finance'],
        'normalized_json' => [
            'fields' => [],
            'meta' => [
                'team_department' => [
                    'type' => 'text',
                    'label' => 'Respondent team or department',
                    'raw' => 'Finance',
                    'normalized' => 'finance',
                ],
            ],
        ],
    ]);

    $args = [
        'organization' => $organization,
        'engagement' => $engagement,
        'metric' => $metric,
    ];

    // Marketing is already an open readiness gap, but the plan is current.
    $this->actingAs($staff)
        ->postJson(route('org.engagements.metrics.prep.facilitation.store', $args))
        ->assertCreated()
        ->assertJsonPath('data.needs_source_review', false);

    // A template change opens a new readiness gap (Sales) without touching the
    // alignment brief, so the persisted plan must be flagged for review.
    $schema = $template->form_schema_json;
    $schema['required_departments'] = ['Finance', 'Marketing', 'Sales'];
    $template->update(['form_schema_json' => $schema]);

    $this->actingAs($staff)
        ->get(route('org.engagements.metrics.prep', $args))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('facilitation_brief.needs_source_review', true)
            ->where('facilitation_brief.status', 'needs_source_review')
        );
});

it('forbids cross-organization access to facilitation endpoints', function () {
    $staff = facilitationStaffUser();
    [$organization, $engagement, $metric] = facilitationMetric();
    $otherOrganization = Organization::factory()->create();

    $this->actingAs($staff)
        ->postJson(route('org.engagements.metrics.prep.facilitation.store', [
            'organization' => $otherOrganization,
            'engagement' => $engagement,
            'metric' => $metric,
        ]))
        ->assertNotFound();
});

it('preserves consultant-authored content across source invalidation', function () {
    $staff = facilitationStaffUser();
    [$organization, $engagement, $metric] = facilitationMetric();

    $args = [
        'organization' => $organization,
        'engagement' => $engagement,
        'metric' => $metric,
    ];

    $created = $this->actingAs($staff)
        ->postJson(route('org.engagements.metrics.prep.facilitation.store', $args))
        ->assertCreated();

    $document = $created->json('data.document');
    $document['run_of_show'][0]['purpose']['text'] = 'Consultant-authored opening I want to keep.';
    $document['run_of_show'][0]['purpose']['provenance'] = 'consultant_authored';

    $this->actingAs($staff)
        ->patchJson(route('org.engagements.metrics.prep.facilitation.update', $args), [
            'revision' => 1,
            'status' => 'draft',
            'run_of_show' => $document['run_of_show'],
            'expected_outputs' => $document['expected_outputs'],
        ])
        ->assertOk();

    // Simulate a canonical source change after preparation.
    MetricFacilitationBrief::where('metric_id', $metric->id)
        ->update(['source_fingerprint' => 'stale-fingerprint']);

    $this->actingAs($staff)
        ->get(route('org.engagements.metrics.prep', $args))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('facilitation_brief.needs_source_review', true)
            ->where('facilitation_brief.status', 'needs_source_review')
            ->where(
                'facilitation_brief.document.run_of_show.0.purpose.text',
                'Consultant-authored opening I want to keep.',
            )
            ->where(
                'facilitation_brief.document.run_of_show.0.purpose.provenance',
                'consultant_authored',
            )
        );
});

it('does not mutate metric state when preparing or saving a facilitation plan', function () {
    $staff = facilitationStaffUser();
    [$organization, $engagement, $metric] = facilitationMetric();
    $statusBefore = $metric->status;

    $args = [
        'organization' => $organization,
        'engagement' => $engagement,
        'metric' => $metric,
    ];

    $created = $this->actingAs($staff)
        ->postJson(route('org.engagements.metrics.prep.facilitation.store', $args))
        ->assertCreated();

    $document = $created->json('data.document');

    $this->actingAs($staff)
        ->patchJson(route('org.engagements.metrics.prep.facilitation.update', $args), [
            'revision' => 1,
            'status' => 'ready_for_workshop',
            'run_of_show' => $document['run_of_show'],
            'expected_outputs' => $document['expected_outputs'],
        ])
        ->assertOk();

    expect($metric->fresh()->status)->toBe($statusBefore);
});

it('forbids non-staff members from preparing a facilitation plan', function () {
    [$organization, $engagement, $metric] = facilitationMetric();

    $member = User::factory()->create();
    Membership::factory()
        ->for($organization)
        ->for($member, 'user')
        ->role(MembershipRole::MEMBER)
        ->create();

    $this->actingAs($member)
        ->postJson(route('org.engagements.metrics.prep.facilitation.store', [
            'organization' => $organization,
            'engagement' => $engagement,
            'metric' => $metric,
        ]))
        ->assertForbidden();
});

it('regenerates the run of show from the current decisions and discards plan edits', function () {
    $staff = facilitationStaffUser();
    [$organization, $engagement, $metric] = facilitationMetric();

    $create = $this->actingAs($staff)
        ->postJson(route('org.engagements.metrics.prep.facilitation.store', [
            'organization' => $organization,
            'engagement' => $engagement,
            'metric' => $metric,
        ]))
        ->assertCreated();

    expect($create->json('data.revision'))->toBe(1)
        ->and($create->json('data.document.run_of_show.0.title'))
        ->toBe('Frame the metric and alignment goal');

    // Simulate a consultant edit to the persisted plan.
    $brief = $metric->fresh()->facilitationBrief;
    $document = $brief->document;
    $document['run_of_show'][0]['title'] = 'My custom opener';
    $document['run_of_show'][0]['purpose']['provenance'] = 'consultant_authored';
    $brief->forceFill(['document' => $document])->save();

    $regenerate = $this->actingAs($staff)
        ->postJson(route('org.engagements.metrics.prep.facilitation.store', [
            'organization' => $organization,
            'engagement' => $engagement,
            'metric' => $metric,
        ]), ['regenerate' => true, 'revision' => 1])
        ->assertOk();

    // The plan is rebuilt from the decisions, the edit is gone, revision bumps.
    expect($regenerate->json('data.revision'))->toBe(2)
        ->and($regenerate->json('data.status'))->toBe('draft')
        ->and($regenerate->json('data.document.run_of_show.0.title'))
        ->toBe('Frame the metric and alignment goal');

    expect(MetricFacilitationBrief::where('metric_id', $metric->id)->count())->toBe(1);
});

it('rejects regeneration with a stale revision', function () {
    $staff = facilitationStaffUser();
    [$organization, $engagement, $metric] = facilitationMetric();

    $this->actingAs($staff)
        ->postJson(route('org.engagements.metrics.prep.facilitation.store', [
            'organization' => $organization,
            'engagement' => $engagement,
            'metric' => $metric,
        ]))
        ->assertCreated();

    $this->actingAs($staff)
        ->postJson(route('org.engagements.metrics.prep.facilitation.store', [
            'organization' => $organization,
            'engagement' => $engagement,
            'metric' => $metric,
        ]), ['regenerate' => true, 'revision' => 99])
        ->assertStatus(409);

    expect($metric->fresh()->facilitationBrief->revision)->toBe(1);
});
