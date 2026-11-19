<?php

use App\Models\FinalDefinition;
use App\Models\Metric;
use App\Models\MetricTemplate;
use App\Models\OrgFinalDefinition;
use App\Models\OrgMetric;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('persists consultant signal fields on final and org final definitions', function () {
    expect(Schema::hasColumns('final_definitions', [
        'operational_status',
        'operational_status_notes',
        'alignment_summary',
        'implementation_gaps',
        'validation_steps',
        'common_mismatch_causes',
        'executive_takeaway',
    ]))->toBeTrue();

    expect(Schema::hasColumns('org_final_definitions', [
        'operational_status',
        'operational_status_notes',
        'alignment_summary',
        'implementation_gaps',
        'validation_steps',
        'common_mismatch_causes',
        'executive_takeaway',
    ]))->toBeTrue();

    $user = User::factory()->create();
    $metric = Metric::factory()->create();

    $finalDefinition = FinalDefinition::query()->create([
        'metric_id' => $metric->getKey(),
        'user_id' => $user->getKey(),
        'version' => 1,
        'status' => 'Draft',
        'definition_json' => [
            'summary' => ['label' => 'Summary', 'value' => 'Canonical summary'],
        ],
        'notes' => 'Definition notes',
        'operational_status' => 'needs_follow_up',
        'operational_status_notes' => 'Warehouse mapping still needs a client-side check.',
        'alignment_summary' => 'Finance and product agreed to use contracted ARR at period end.',
        'validation_steps' => 'Compare workbook totals to the board pack.',
        'common_mismatch_causes' => 'Late renewal backfills can skew the dashboard.',
        'implementation_gaps' => [
            'current_state' => 'Dashboard uses invoice date.',
            'gap' => 'Definition requires contracted period-end ARR.',
            'owner' => 'Finance systems',
            'next_step' => 'Update dashboard logic after close.',
        ],
        'executive_takeaway' => 'This is the final definition takeaway.',
    ]);

    $orgMetric = OrgMetric::factory()->create();
    $template = MetricTemplate::factory()->create([
        'canonical_metric_id' => $orgMetric->canonical_metric_id,
        'org_metric_id' => $orgMetric->getKey(),
        'key' => $orgMetric->key,
        'name' => $orgMetric->name,
    ]);
    $sourceMetric = Metric::factory()->create([
        'organization_id' => $orgMetric->organization_id,
        'engagement_id' => null,
        'org_metric_id' => $orgMetric->getKey(),
        'metric_template_id' => $template->getKey(),
        'key' => $orgMetric->key,
        'name' => $orgMetric->name,
    ]);

    $orgFinalDefinition = OrgFinalDefinition::query()->create([
        'org_metric_id' => $orgMetric->getKey(),
        'source_metric_id' => $sourceMetric->getKey(),
        'source_metric_template_id' => $template->getKey(),
        'version' => 1,
        'status' => 'Approved',
        'definition_json' => [
            'summary' => ['label' => 'Summary', 'value' => 'Org-level official summary'],
        ],
        'notes' => 'Org-level notes',
        'operational_status' => 'operationally_ready',
        'operational_status_notes' => 'Approved dashboard already uses the signed definition.',
        'alignment_summary' => 'Board and finance pack are now aligned on the same label and cutoff.',
        'validation_steps' => 'Reconcile dashboard totals against the finance workbook.',
        'common_mismatch_causes' => 'Internal tenants can remain in the published report.',
        'implementation_gaps' => [
            'current_state' => 'BI dashboard matches approved metric.',
            'gap' => 'None currently open.',
            'owner' => 'Revenue ops',
            'next_step' => 'Review again next quarter.',
        ],
        'executive_takeaway' => 'This is the org final definition takeaway.',
    ]);

    $finalDefinition->refresh();
    $orgFinalDefinition->refresh();

    expect($finalDefinition->operational_status)->toBe('needs_follow_up')
        ->and($finalDefinition->operational_status_notes)->toBe('Warehouse mapping still needs a client-side check.')
        ->and($finalDefinition->alignment_summary)->toBe('Finance and product agreed to use contracted ARR at period end.')
        ->and($finalDefinition->validation_steps)->toBe('Compare workbook totals to the board pack.')
        ->and($finalDefinition->common_mismatch_causes)->toBe('Late renewal backfills can skew the dashboard.')
        ->and($finalDefinition->executive_takeaway)->toBe('This is the final definition takeaway.')
        ->and($finalDefinition->implementation_gaps)->toBeArray()
        ->and($finalDefinition->implementation_gaps['owner'])->toBe('Finance systems');

    expect($orgFinalDefinition->operational_status)->toBe('operationally_ready')
        ->and($orgFinalDefinition->operational_status_notes)->toBe('Approved dashboard already uses the signed definition.')
        ->and($orgFinalDefinition->alignment_summary)->toBe('Board and finance pack are now aligned on the same label and cutoff.')
        ->and($orgFinalDefinition->validation_steps)->toBe('Reconcile dashboard totals against the finance workbook.')
        ->and($orgFinalDefinition->common_mismatch_causes)->toBe('Internal tenants can remain in the published report.')
        ->and($orgFinalDefinition->executive_takeaway)->toBe('This is the org final definition takeaway.')
        ->and($orgFinalDefinition->implementation_gaps)->toBeArray()
        ->and($orgFinalDefinition->implementation_gaps['owner'])->toBe('Revenue ops');
});

it('allows consultant signal fields to remain null on final and org final definitions', function () {
    $user = User::factory()->create();
    $metric = Metric::factory()->create();

    $finalDefinition = FinalDefinition::query()->create([
        'metric_id' => $metric->getKey(),
        'user_id' => $user->getKey(),
        'version' => 1,
        'status' => 'Draft',
        'definition_json' => [],
        'notes' => null,
        'operational_status' => null,
        'operational_status_notes' => null,
        'alignment_summary' => null,
        'implementation_gaps' => null,
        'validation_steps' => null,
        'common_mismatch_causes' => null,
        'executive_takeaway' => null,
    ]);

    $orgMetric = OrgMetric::factory()->create();
    $template = MetricTemplate::factory()->create([
        'canonical_metric_id' => $orgMetric->canonical_metric_id,
        'org_metric_id' => $orgMetric->getKey(),
        'key' => $orgMetric->key,
        'name' => $orgMetric->name,
    ]);
    $sourceMetric = Metric::factory()->create([
        'organization_id' => $orgMetric->organization_id,
        'engagement_id' => null,
        'org_metric_id' => $orgMetric->getKey(),
        'metric_template_id' => $template->getKey(),
        'key' => $orgMetric->key,
        'name' => $orgMetric->name,
    ]);

    $orgFinalDefinition = OrgFinalDefinition::query()->create([
        'org_metric_id' => $orgMetric->getKey(),
        'source_metric_id' => $sourceMetric->getKey(),
        'source_metric_template_id' => $template->getKey(),
        'version' => 1,
        'status' => 'PendingApproval',
        'definition_json' => [],
        'notes' => null,
        'operational_status' => null,
        'operational_status_notes' => null,
        'alignment_summary' => null,
        'implementation_gaps' => null,
        'validation_steps' => null,
        'common_mismatch_causes' => null,
        'executive_takeaway' => null,
    ]);

    $finalDefinition->refresh();
    $orgFinalDefinition->refresh();

    expect($finalDefinition->operational_status)->toBeNull()
        ->and($finalDefinition->operational_status_notes)->toBeNull()
        ->and($finalDefinition->alignment_summary)->toBeNull()
        ->and($finalDefinition->implementation_gaps)->toBeNull()
        ->and($finalDefinition->validation_steps)->toBeNull()
        ->and($finalDefinition->common_mismatch_causes)->toBeNull()
        ->and($finalDefinition->executive_takeaway)->toBeNull();

    expect($orgFinalDefinition->operational_status)->toBeNull()
        ->and($orgFinalDefinition->operational_status_notes)->toBeNull()
        ->and($orgFinalDefinition->alignment_summary)->toBeNull()
        ->and($orgFinalDefinition->implementation_gaps)->toBeNull()
        ->and($orgFinalDefinition->validation_steps)->toBeNull()
        ->and($orgFinalDefinition->common_mismatch_causes)->toBeNull()
        ->and($orgFinalDefinition->executive_takeaway)->toBeNull();
});

it('provides reusable consultant signal factory states for handbook fixtures', function () {
    $finalDefinition = FinalDefinition::factory()->withConsultantSignals()->make();
    $orgFinalDefinition = OrgFinalDefinition::factory()->withConsultantSignals([
        'alignment_summary' => null,
        'implementation_gaps' => null,
    ])->make();

    expect($finalDefinition->operational_status)->toBe('needs_follow_up')
        ->and($finalDefinition->operational_status_notes)->toBe('Dashboard filters still need one client-side validation pass.')
        ->and($finalDefinition->alignment_summary)->toBe('Finance, product, and operations aligned on one board-facing definition.')
        ->and($finalDefinition->validation_steps)->toBe('Compare workbook totals to the approved board pack.')
        ->and($finalDefinition->common_mismatch_causes)->toBe('Dashboard filters and late source-system backfills can drift from the signed definition.')
        ->and($finalDefinition->executive_takeaway)->toBe('Authored executive takeaway for the metric.')
        ->and($finalDefinition->implementation_gaps)->toBe([
            'current_state' => 'Definition is documented, but the published report still depends on a manual reconciliation step.',
            'gap' => 'Dashboard logic needs one client-side update to match the signed definition.',
            'owner' => 'Revenue ops',
            'suggested_next_step' => 'Update the report before the next review cycle.',
        ]);

    expect($orgFinalDefinition->operational_status)->toBe('needs_follow_up')
        ->and($orgFinalDefinition->alignment_summary)->toBeNull()
        ->and($orgFinalDefinition->implementation_gaps)->toBeNull()
        ->and($orgFinalDefinition->validation_steps)->toBe('Compare workbook totals to the approved board pack.')
        ->and($orgFinalDefinition->common_mismatch_causes)->toBe('Dashboard filters and late source-system backfills can drift from the signed definition.')
        ->and($orgFinalDefinition->executive_takeaway)->toBe('Authored executive takeaway for the metric.');
});
