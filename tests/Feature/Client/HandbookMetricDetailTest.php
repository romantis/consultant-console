<?php

use App\Enums\MembershipRole;
use App\Enums\MetricStatus;
use App\Models\Discrepancy;
use App\Models\Engagement;
use App\Models\Membership;
use App\Models\Metric;
use App\Models\MetricDefinition;
use App\Models\Organization;
use App\Models\OrgFinalDefinition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns read-only handbook metric detail data without raw discrepancy payloads', function () {
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->for($organization)->create();
    $viewer = User::factory()->create();

    Membership::factory()
        ->for($organization)
        ->for($viewer, 'user')
        ->role(MembershipRole::VIEWER)
        ->create();

    $metric = Metric::factory()
        ->for($organization)
        ->for($engagement)
        ->state(['status' => MetricStatus::IN_REVIEW])
        ->create();

    $definitionAuthor = User::factory()->create();

    MetricDefinition::factory()
        ->for($metric)
        ->for($definitionAuthor, 'user')
        ->create([
            'normalized_json' => [
                'fields' => [
                    'value' => [
                        'type' => 'number',
                        'raw' => 120,
                        'normalized' => 120.0,
                        'label' => 'Metric Value',
                    ],
                ],
                'meta' => [],
            ],
        ]);

    Discrepancy::factory()
        ->for($metric)
        ->create([
            'field_key' => 'value',
            'severity' => 'medium',
            'status' => 'Open',
        ]);

    OrgFinalDefinition::factory()
        ->withConsultantSignals([
            'alignment_summary' => 'Prior alignment summary.',
            'operational_status' => 'operationally_ready',
            'operational_status_notes' => 'Prior reporting already matched.',
            'implementation_gaps' => null,
        ])
        ->create([
            'org_metric_id' => $metric->org_metric_id,
            'source_metric_id' => $metric->id,
            'source_metric_template_id' => $metric->metric_template_id,
            'status' => 'Approved',
            'signed_off_at' => now()->subDays(3),
            'signed_off_by' => $definitionAuthor->id,
            'version' => 1,
            'definition_json' => [
                'summary' => ['label' => 'Summary', 'value' => 'Official v1'],
            ],
        ]);

    $currentOfficial = OrgFinalDefinition::factory()
        ->withConsultantSignals([
            'alignment_summary' => 'Finance and product aligned on a single board-facing definition.',
            'operational_status' => 'needs_follow_up',
            'operational_status_notes' => 'Dashboard filters still need one tenant exclusion.',
            'implementation_gaps' => [
                'current_state' => 'Board pack still relies on a workbook export.',
                'gap' => 'Internal tenants remain in the dashboard.',
                'owner' => 'Revenue ops',
                'suggested_next_step' => 'Ship the tenant exclusion before the next close.',
            ],
        ])
        ->create([
            'org_metric_id' => $metric->org_metric_id,
            'source_metric_id' => $metric->id,
            'source_metric_template_id' => $metric->metric_template_id,
            'status' => 'Approved',
            'signed_off_at' => now()->subDays(2),
            'signed_off_by' => $definitionAuthor->id,
            'version' => 2,
            'definition_json' => [
                'summary' => ['label' => 'Summary', 'value' => 'Official v2'],
            ],
        ]);

    $this->actingAs($viewer)
        ->getJson(route('client.handbook.metrics.show', [$organization, $engagement, $metric]))
        ->assertOk()
        ->assertJsonPath('org_metric.id', (string) $metric->org_metric_id)
        ->assertJsonPath('engagement_metric.metric.id', (string) $metric->id)
        ->assertJsonPath('official_definition.id', (string) $currentOfficial->id)
        ->assertJsonPath('official_definition.alignment_summary', 'Finance and product aligned on a single board-facing definition.')
        ->assertJsonPath('official_definition.operational_status', 'needs_follow_up')
        ->assertJsonPath('official_definition.operational_status_notes', 'Dashboard filters still need one tenant exclusion.')
        ->assertJsonPath('official_definition.implementation_gaps.owner', 'Revenue ops')
        ->assertJsonCount(1, 'official_definition_history')
        ->assertJsonPath('official_definition_history.0.version', 1)
        ->assertJsonPath('official_definition_history.0.signed_off_by.name', $definitionAuthor->name)
        ->assertJsonMissingPath('metric')
        ->assertJsonMissingPath('draft_definition')
        ->assertJsonMissingPath('final_definition')
        ->assertJsonMissingPath('final_definition_history')
        ->assertJsonMissingPath('owner_options')
        ->assertJsonMissingPath('approver_options')
        ->assertJsonMissingPath('permissions')
        ->assertJsonMissingPath('engagement_metric.metric.matrix')
        ->assertJsonMissingPath('engagement_metric.metric.context_fields')
        ->assertJsonMissingPath('engagement_metric.metric.definitions');
});

it('returns not found when the handbook metric detail route engagement does not match the metric', function () {
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->for($organization)->create();
    $otherEngagement = Engagement::factory()->for($organization)->create();
    $viewer = User::factory()->create();

    Membership::factory()
        ->for($organization)
        ->for($viewer, 'user')
        ->role(MembershipRole::VIEWER)
        ->create();

    $metric = Metric::factory()
        ->for($organization)
        ->for($otherEngagement)
        ->create();

    $this->actingAs($viewer)
        ->getJson(route('client.handbook.metrics.show', [$organization, $engagement, $metric]))
        ->assertNotFound();
});
