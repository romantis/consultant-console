<?php

use App\Actions\FormAssignments\CompleteAssignment;
use App\Actions\Metrics\BuildMetricAlignmentBrief;
use App\Actions\Metrics\UpdateMetricTemplate;
use App\Enums\EngagementStatus;
use App\Enums\MembershipRole;
use App\Enums\MetricStatus;
use App\Models\CanonicalMetric;
use App\Models\Discrepancy;
use App\Models\Engagement;
use App\Models\Form;
use App\Models\FormAssignment;
use App\Models\FormSubmission;
use App\Models\Membership;
use App\Models\Metric;
use App\Models\MetricAlignmentDraft;
use App\Models\MetricDefinition;
use App\Models\MetricParticipant;
use App\Models\MetricTemplate;
use App\Models\Organization;
use App\Models\User;
use App\Support\Metrics\Drafting\BriefFingerprint;
use App\Support\Metrics\MetricDefinitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function getBriefFingerprint(Metric $metric): string
{
    $brief = app(BuildMetricAlignmentBrief::class)->build($metric);

    return BriefFingerprint::calculate($brief);
}

function validWorkshopPrepGeneratedDraft(Metric $metric): array
{
    return [
        'schema_version' => '1.0',
        'status' => 'draft',
        'metric_id' => (string) $metric->id,
        'metric_name' => $metric->name,
        'summary' => [
            'text' => 'The required metric value still needs source evidence.',
            'citation_ids' => ['field_schema:value'],
        ],
        'resolution_focus' => [[
            'field_key' => 'value',
            'kind' => 'missing_evidence',
            'title' => 'Confirm the metric value evidence',
            'synthesis' => 'No submitted value is available for comparison.',
            'source_item_ids' => ['field:value:missing_evidence'],
            'citation_ids' => ['field_schema:value'],
            'facilitator_questions' => [[
                'question' => 'What source should establish the metric value?',
                'reason' => 'The required field has no submitted evidence.',
                'source_question_ids' => ['question:value'],
                'citation_ids' => ['field_schema:value'],
            ]],
            'candidate_resolution_paths' => [],
        ]],
        'supporting_context' => [],
    ];
}

it('forbids non-staff users from opening the workshop prep page', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
    ]);
    $metric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
    ]);

    Membership::factory()
        ->for($organization)
        ->for($user, 'user')
        ->role(MembershipRole::MEMBER)
        ->create();

    MetricParticipant::factory()->create([
        'metric_id' => $metric->id,
        'user_id' => $user->id,
        'responsibility' => 'contributor',
    ]);

    $this->actingAs($user)
        ->get(route('org.engagements.metrics.prep', [
            'organization' => $organization,
            'engagement' => $engagement,
            'metric' => $metric,
        ]))
        ->assertForbidden();
});

it('allows staff users to open the workshop prep page and returns correct payload', function () {
    $staff = User::factory()->create();
    $internal = Organization::factory()->create(['is_internal' => true]);

    Membership::factory()
        ->for($internal, 'organization')
        ->for($staff, 'user')
        ->admin()
        ->create();

    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
    ]);
    $metric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
    ]);

    $this->actingAs($staff)
        ->get(route('org.engagements.metrics.prep', [
            'organization' => $organization,
            'engagement' => $engagement,
            'metric' => $metric,
        ]))
        ->assertInertia(fn ($page) => $page
            ->component('Metrics/WorkshopPrep')
            ->has('organization')
            ->has('engagement')
            ->has('metric')
            ->has('metric.field_roles')
            ->has('metric_alignment_brief')
            ->has('metric_alignment_brief.summary')
            ->has('metric_alignment_brief.material_conflicts')
            ->has('metric_alignment_brief.missing_evidence')
            ->has('metric_alignment_brief.recommended_questions')
            // The private, operator-only readiness pack is exposed on this
            // staff-gated prep route (spec 037 Task 4).
            ->has('facilitator_readiness_pack')
            ->where('facilitator_readiness_pack.schema_version', '0.1')
            ->where('facilitator_readiness_pack.provenance', 'deterministic')
            ->has('facilitator_readiness_pack.readiness')
            ->has('facilitator_readiness_pack.decisions')
            ->has('facilitator_readiness_pack.capture_checklist')
        );
});

it('enforces organization scope mismatch protections', function () {
    $staff = User::factory()->create();
    $internal = Organization::factory()->create(['is_internal' => true]);

    Membership::factory()
        ->for($internal, 'organization')
        ->for($staff, 'user')
        ->admin()
        ->create();

    $organization1 = Organization::factory()->create();
    $organization2 = Organization::factory()->create();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization1->id,
    ]);
    $metric = Metric::factory()->create([
        'organization_id' => $organization1->id,
        'engagement_id' => $engagement->id,
    ]);

    // Mismatched organization in URL (organization2 != organization1)
    $this->actingAs($staff)
        ->get(route('org.engagements.metrics.prep', [
            'organization' => $organization2,
            'engagement' => $engagement,
            'metric' => $metric,
        ]))
        ->assertNotFound();
});

it('enforces engagement scope mismatch protections', function () {
    $staff = User::factory()->create();
    $internal = Organization::factory()->create(['is_internal' => true]);

    Membership::factory()
        ->for($internal, 'organization')
        ->for($staff, 'user')
        ->admin()
        ->create();

    $organization = Organization::factory()->create();
    $engagement1 = Engagement::factory()->create([
        'organization_id' => $organization->id,
    ]);
    $engagement2 = Engagement::factory()->create([
        'organization_id' => $organization->id,
    ]);
    $metric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement1->id,
    ]);

    // Mismatched engagement in URL (engagement2 != engagement1)
    $this->actingAs($staff)
        ->get(route('org.engagements.metrics.prep', [
            'organization' => $organization,
            'engagement' => $engagement2,
            'metric' => $metric,
        ]))
        ->assertNotFound();
});

it('ensures the old discrepancies page route is absent and returns 404', function () {
    $staff = User::factory()->create();
    $internal = Organization::factory()->create(['is_internal' => true]);

    Membership::factory()
        ->for($internal, 'organization')
        ->for($staff, 'user')
        ->admin()
        ->create();

    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
    ]);
    $metric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
    ]);

    expect(Route::has('org.metrics.discrepancies'))->toBeFalse();

    $this->actingAs($staff)
        ->get("/organizations/{$organization->id}/metrics/{$metric->id}/discrepancies")
        ->assertNotFound();
});

it('allows description to be null on alignment signal', function () {
    $staff = User::factory()->create();
    $internal = Organization::factory()->create(['is_internal' => true]);

    Membership::factory()
        ->for($internal, 'organization')
        ->for($staff, 'user')
        ->admin()
        ->create();

    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
    ]);
    $metric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
    ]);

    // Create a discrepancy with null description
    Discrepancy::factory()->create([
        'metric_id' => $metric->id,
        'engagement_id' => $engagement->id,
        'field_key' => 'value',
        'description' => null,
    ]);

    $this->actingAs($staff)
        ->get(route('org.engagements.metrics.prep', [
            'organization' => $organization,
            'engagement' => $engagement,
            'metric' => $metric,
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Metrics/WorkshopPrep')
            ->where('metric.field_roles.0.alignment_signal.description', null)
        );
});

it('excludes draft definitions from values payload and brief counts', function () {
    $staff = User::factory()->create();
    $internal = Organization::factory()->create(['is_internal' => true]);

    Membership::factory()
        ->for($internal, 'organization')
        ->for($staff, 'user')
        ->admin()
        ->create();

    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
    ]);
    $metric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
    ]);

    // Create a submitted definition
    $submittedDefinition = MetricDefinition::factory()->for($metric)->create([
        'status' => 'Submitted',
        'normalized_json' => [
            'fields' => [
                'value' => [
                    'type' => 'number',
                    'raw' => 100,
                    'normalized' => 100,
                    'label' => 'Metric Value',
                ],
            ],
        ],
    ]);

    // Create a draft definition
    $draftDefinition = MetricDefinition::factory()->for($metric)->create([
        'status' => 'Draft',
        'normalized_json' => [
            'fields' => [
                'value' => [
                    'type' => 'number',
                    'raw' => 200,
                    'normalized' => 200,
                    'label' => 'Metric Value',
                ],
            ],
        ],
    ]);

    $this->actingAs($staff)
        ->get(route('org.engagements.metrics.prep', [
            'organization' => $organization,
            'engagement' => $engagement,
            'metric' => $metric,
        ]))
        ->assertOk()
        ->assertInertia(function ($page) use ($submittedDefinition, $draftDefinition) {
            $page->component('Metrics/WorkshopPrep');

            // Check that the submitted answers count is 1
            $page->where('metric_alignment_brief.summary.source_counts.submitted_answers', 1);

            // Check that only the submitted definition's values are present
            $fieldRoles = $page->toArray()['props']['metric']['field_roles'];
            $valueField = collect($fieldRoles)->firstWhere('key', 'value');

            expect($valueField)->not->toBeNull();
            expect($valueField['values'])->toHaveCount(1);
            expect($valueField['values'][0]['definition_id'])->toBe((string) $submittedDefinition->id);
            expect($valueField['values'][0]['raw'])->toBe(100);

            // Draft definition ID should NOT be present in values
            $definitionIdsInValues = collect($valueField['values'])->pluck('definition_id')->all();
            expect($definitionIdsInValues)->not->toContain((string) $draftDefinition->id);
        });
});

it('derives the readiness pack deterministically from the same brief the prep page renders', function () {
    $staff = User::factory()->create();
    $internal = Organization::factory()->create(['is_internal' => true]);

    Membership::factory()
        ->for($internal, 'organization')
        ->for($staff, 'user')
        ->admin()
        ->create();

    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->create(['organization_id' => $organization->id]);
    $metric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
    ]);

    // Two submitted, divergent values so the brief surfaces a real decision.
    foreach ([100, 200] as $value) {
        MetricDefinition::factory()->for($metric)->create([
            'status' => 'Submitted',
            'normalized_json' => [
                'fields' => [
                    'value' => ['type' => 'number', 'raw' => $value, 'normalized' => $value, 'label' => 'Metric Value'],
                ],
            ],
        ]);
    }

    $this->actingAs($staff)
        ->get(route('org.engagements.metrics.prep', [
            'organization' => $organization,
            'engagement' => $engagement,
            'metric' => $metric,
        ]))
        ->assertOk()
        ->assertInertia(function ($page) use ($metric) {
            $props = $page->toArray()['props'];
            $brief = $props['metric_alignment_brief'];
            $pack = $props['facilitator_readiness_pack'];

            // The pack is bound to the scoped metric and built without a provider.
            expect($pack['metric_id'])->toBe((string) $metric->id)
                ->and($pack['provenance'])->toBe('deterministic')
                ->and($pack['generated_at'])->toBeNull();

            // Every attention item in the brief becomes exactly one pack decision,
            // proving the controller fed the real brief through, not a stub.
            $expectedDecisionCount = count($brief['material_conflicts'])
                + count($brief['missing_evidence'])
                + count($brief['decision_needed']);

            expect($pack['decisions'])->toHaveCount($expectedDecisionCount)
                ->and($pack['readiness']['decision_count'])->toBe($expectedDecisionCount);

            // Each decision points at a field the brief actually carries.
            $briefFieldKeys = collect($brief['material_conflicts'])
                ->merge($brief['missing_evidence'])
                ->merge($brief['decision_needed'])
                ->pluck('field_key')
                ->all();

            foreach ($pack['decisions'] as $decision) {
                expect($decision['field_key'])->toBeIn($briefFieldKeys);
            }
        });
});

it('invalidates draft when a definition is saved with submitted status or deleted', function () {
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
    ]);
    $metric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
    ]);

    $draft = validWorkshopPrepGeneratedDraft($metric);

    // 1. Create a draft in database
    MetricAlignmentDraft::create([
        'metric_id' => $metric->id,
        'profile_version' => config('metric-alignment-drafting.profile.version'),
        'prompt_version' => config('metric-alignment-drafting.prompt_version'),
        'provider' => 'openrouter',
        'requested_model' => 'test-model',
        'returned_model' => 'test-model',
        'source_fingerprint' => getBriefFingerprint($metric),
        'draft' => $draft,
        'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 10],
    ]);

    expect(MetricAlignmentDraft::count())->toBe(1);

    // 2. Create a definition with status 'Draft' -> should NOT invalidate draft
    $definition = MetricDefinition::create([
        'metric_id' => $metric->id,
        'user_id' => User::factory()->create()->id,
        'source_type' => 'form_submission',
        'status' => 'Draft',
        'definition_json' => [],
        'normalized_json' => [],
    ]);

    expect(MetricAlignmentDraft::count())->toBe(1);

    // 3. Update status to 'Submitted' -> should invalidate draft
    $definition->update(['status' => 'Submitted']);

    expect(MetricAlignmentDraft::count())->toBe(0);

    // Recreate draft
    MetricAlignmentDraft::create([
        'metric_id' => $metric->id,
        'profile_version' => config('metric-alignment-drafting.profile.version'),
        'prompt_version' => config('metric-alignment-drafting.prompt_version'),
        'provider' => 'openrouter',
        'requested_model' => 'test-model',
        'returned_model' => 'test-model',
        'source_fingerprint' => getBriefFingerprint($metric),
        'draft' => $draft,
        'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 10],
    ]);

    expect(MetricAlignmentDraft::count())->toBe(1);

    // 4. Delete definition -> should invalidate draft
    $definition->delete();

    expect(MetricAlignmentDraft::count())->toBe(0);
});

it('cascades deletion of the alignment draft when the metric is deleted', function () {
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
    ]);
    $metric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
    ]);
    $draft = validWorkshopPrepGeneratedDraft($metric);

    MetricAlignmentDraft::create([
        'metric_id' => $metric->id,
        'profile_version' => config('metric-alignment-drafting.profile.version'),
        'prompt_version' => config('metric-alignment-drafting.prompt_version'),
        'provider' => 'openrouter',
        'requested_model' => 'test-model',
        'returned_model' => 'test-model',
        'source_fingerprint' => getBriefFingerprint($metric),
        'draft' => $draft,
        'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 10],
    ]);

    expect(MetricAlignmentDraft::count())->toBe(1);

    if (DB::connection()->getDriverName() === 'sqlite') {
        DB::connection()->getPdo()->exec('PRAGMA foreign_keys = ON');
    }

    // Force delete the metric to trigger database cascade
    $metric->forceDelete();

    // The associated alignment draft should be cascade deleted
    expect(MetricAlignmentDraft::count())->toBe(0);
});

it('invalidates the generated draft when a real CompleteAssignment completes a form submission', function () {
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
    ]);
    $metric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
    ]);

    $draft = validWorkshopPrepGeneratedDraft($metric);

    // 1. Create a draft in database
    MetricAlignmentDraft::create([
        'metric_id' => $metric->id,
        'profile_version' => config('metric-alignment-drafting.profile.version'),
        'prompt_version' => config('metric-alignment-drafting.prompt_version'),
        'provider' => 'openrouter',
        'requested_model' => 'test-model',
        'returned_model' => 'test-model',
        'source_fingerprint' => getBriefFingerprint($metric),
        'draft' => $draft,
        'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 10],
    ]);

    expect(MetricAlignmentDraft::count())->toBe(1);

    // 2. Setup a form, assignment, and submission
    $user = User::factory()->create();
    $form = Form::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'metric_id' => $metric->id,
        'schema_json' => [
            'fields' => [
                [
                    'name' => 'overhead_handling',
                    'type' => 'text',
                    'required' => true,
                ],
            ],
        ],
    ]);

    $assignment = FormAssignment::factory()->create([
        'form_id' => $form->id,
        'user_id' => $user->id,
    ]);

    $submission = FormSubmission::factory()->create([
        'form_id' => $form->id,
        'user_id' => $user->id,
    ]);

    // 3. Submit the assignment through CompleteAssignment collaborator
    app(CompleteAssignment::class)->handle(
        assignment: $assignment,
        submission: $submission,
        actor: $user,
        payload: ['overhead_handling' => 'Use head-count method.'],
        attestation: null,
        submissionToken: null
    );

    // 4. Verification: The draft must have been invalidated (deleted)
    expect(MetricAlignmentDraft::count())->toBe(0);
});

it('increments brief_source_revision on the metric when definition, discrepancy, or template changes', function () {
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
    ]);
    $template = MetricTemplate::factory()->create([
        'org_metric_id' => null,
    ]);
    $metric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'metric_template_id' => $template->id,
        'brief_source_revision' => 1,
    ]);

    expect($metric->brief_source_revision)->toBe(1);

    // 1. Definition change triggers revision increment
    $submission = FormSubmission::factory()->create([
        'form_id' => Form::factory()->create([
            'metric_id' => $metric->id,
            'organization_id' => $organization->id,
            'engagement_id' => $engagement->id,
        ])->id,
        'user_id' => User::factory()->create()->id,
        'submitted_at' => now(),
    ]);

    app(MetricDefinitionService::class)->recordFromSubmission($submission);

    $revisionAfterDefinition = $metric->fresh()->brief_source_revision;
    expect($revisionAfterDefinition)->toBeGreaterThan(1);

    // 2. Discrepancy change triggers revision increment
    $discrepancy = Discrepancy::create([
        'metric_id' => $metric->id,
        'engagement_id' => $engagement->id,
        'field_key' => 'overhead_handling',
        'rule_type' => 'divergent',
        'severity' => 'medium',
        'status' => 'Open',
        'description' => 'discrepancy desc',
        'values_snapshot' => [],
        'detected_at' => now(),
    ]);

    $staff = User::factory()->create();
    $internal = Organization::factory()->create(['is_internal' => true]);
    Membership::factory()
        ->for($internal, 'organization')
        ->for($staff, 'user')
        ->admin()
        ->create();

    $this->actingAs($staff)
        ->patchJson(route('api.metrics.discrepancies.update', [
            'metric' => $metric,
            'discrepancy' => $discrepancy,
        ]), [
            'status' => 'Resolved',
            'notes' => 'Resolved notes',
        ])
        ->assertOk();

    $revisionAfterDiscrepancy = $metric->fresh()->brief_source_revision;
    expect($revisionAfterDiscrepancy)->toBeGreaterThan($revisionAfterDefinition);

    // 3. Template change triggers revision increment
    app(UpdateMetricTemplate::class)->handle($template, ['name' => 'New Template Name']);

    expect($metric->fresh()->brief_source_revision)->toBeGreaterThan($revisionAfterDiscrepancy);
});

it('enforces concurrent locking protocol during template update preventing race conditions', function () {
    $driver = DB::connection()->getDriverName();
    $dbPath = null;

    if ($driver === 'sqlite') {
        $dbPath = tempnam(sys_get_temp_dir(), 'test_concurrency_');
        unlink($dbPath);
        touch($dbPath);

        config([
            'database.connections.conn1' => [
                'driver' => 'sqlite',
                'database' => $dbPath,
                'foreign_key_constraints' => true,
            ],
            'database.connections.conn2' => [
                'driver' => 'sqlite',
                'database' => $dbPath,
                'foreign_key_constraints' => true,
            ],
        ]);
    } elseif ($driver === 'pgsql') {
        $config = config('database.connections.pgsql');
        config([
            'database.connections.conn1' => $config,
            'database.connections.conn2' => $config,
        ]);
    } else {
        $this->markTestSkipped('Skipping concurrency test for driver: '.$driver);
    }

    try {
        // Run migrations on connection 1
        Artisan::call('migrate', ['--database' => 'conn1']);

        $originalDefault = DB::getDefaultConnection();
        DB::setDefaultConnection('conn1');

        $conn1 = DB::connection('conn1');
        $conn2 = DB::connection('conn2');

        // Create records using default connection (which is conn1)
        $canonical = CanonicalMetric::create([
            'key' => 'concurrency-test-key-'.Str::random(4),
            'name' => 'Concurrency Test',
            'is_active' => true,
        ]);

        $template = MetricTemplate::create([
            'canonical_metric_id' => $canonical->id,
            'key' => 'concurrency-test-key-'.Str::random(4),
            'name' => 'Old Template Name',
            'form_schema_json' => ['fields' => []],
        ]);

        $org = Organization::create([
            'name' => 'Test Org',
            'slug' => 'test-org-'.Str::random(4),
            'is_internal' => false,
        ]);

        $engagement = Engagement::create([
            'organization_id' => $org->id,
            'title' => 'Test Engagement',
            'slug' => 'test-engagement-'.Str::random(4),
            'status' => EngagementStatus::IN_REVIEW,
        ]);

        $metric = Metric::create([
            'organization_id' => $org->id,
            'engagement_id' => $engagement->id,
            'metric_template_id' => $template->id,
            'brief_source_revision' => 1,
            'status' => MetricStatus::IN_REVIEW,
            'name' => 'Concurrency Metric',
            'key' => 'concurrency-metric-key',
        ]);

        // Connection 1: Start transaction and lock the metric row
        $conn1->beginTransaction();
        $conn1->table('metrics')->where('id', $metric->id)->lockForUpdate()->first();

        // Connection 2: Set busy timeout (if SQLite) so it fails quickly instead of blocking the process
        if ($driver === 'sqlite') {
            $conn2->statement('PRAGMA busy_timeout = 100');
        }

        // Attempt template update on Connection 2
        DB::setDefaultConnection('conn2');

        try {
            $templateConn2 = MetricTemplate::find($template->id);
            app(UpdateMetricTemplate::class)->handle($templateConn2, ['name' => 'New Template Name']);
            $this->fail('Expected transaction to fail due to lock concurrency exception.');
        } catch (Throwable $e) {
            $message = strtolower($e->getMessage());
            $isLockError = str_contains($message, 'locked')
                || str_contains($message, 'timeout')
                || str_contains($message, 'deadlock')
                || str_contains($message, 'busy');

            expect($isLockError)->toBeTrue();
        } finally {
            DB::setDefaultConnection($originalDefault);
            $conn1->rollBack();
        }

    } finally {
        if ($dbPath && file_exists($dbPath)) {
            unlink($dbPath);
        }
    }
});
