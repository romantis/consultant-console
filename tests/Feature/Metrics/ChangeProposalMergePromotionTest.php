<?php

use App\Actions\Metrics\MergeChangeProposal;
use App\Enums\ChangeProposalStatus;
use App\Enums\EngagementStatus;
use App\Enums\MetricStatus;
use App\Models\ChangeProposal;
use App\Models\Engagement;
use App\Models\FinalDefinition;
use App\Models\Metric;
use App\Models\MetricParticipant;
use App\Models\Organization;
use App\Models\OrgFinalDefinition;
use App\Models\OrgMetricParticipant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

/**
 * @return array{
 *   consultant: User,
 *   metric: Metric,
 *   final_definition: FinalDefinition,
 *   proposal: ChangeProposal,
 *   prior_official: OrgFinalDefinition|null
 * }
 */
function makeMergePromotionContext(
    array $proposalPayload = [],
    bool $withOrgOwner = true,
    bool $withPriorOfficial = true
): array {
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
        'status' => EngagementStatus::FINALIZED,
    ]);

    $consultant = User::factory()->create();
    $ownerUser = User::factory()->create();

    $metric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'status' => MetricStatus::FINALIZED,
        'name' => 'Net Revenue Retention',
    ]);

    $ownerParticipant = MetricParticipant::factory()->approver()->create([
        'metric_id' => $metric->id,
        'user_id' => $ownerUser->id,
    ]);

    $orgOwner = $withOrgOwner
        ? OrgMetricParticipant::factory()->create([
            'org_metric_id' => $metric->org_metric_id,
            'user_id' => $ownerUser->id,
            'responsibility' => 'owner',
        ])
        : null;

    $finalDefinition = FinalDefinition::factory()->create([
        'metric_id' => $metric->id,
        'user_id' => $consultant->id,
        'version' => 1,
        'status' => 'Approved',
        'definition_json' => [
            'summary' => ['label' => 'Summary', 'value' => 'Baseline throughput'],
            'owner' => ['label' => 'Owner', 'value' => 'RevOps'],
        ],
        'notes' => 'Initial final definition agreed in Q1',
        'validation_steps' => 'Legacy validation steps should be rewritten.',
        'common_mismatch_causes' => 'Legacy mismatch causes should be rewritten.',
        'owner_participant_id' => $ownerParticipant->id,
        'signed_off_at' => now()->subDays(7),
        'signed_off_by' => $ownerUser->id,
    ]);

    $priorOfficial = ($withPriorOfficial && $orgOwner)
        ? OrgFinalDefinition::factory()->create([
            'org_metric_id' => $metric->org_metric_id,
            'source_metric_id' => $metric->id,
            'source_metric_template_id' => $metric->metric_template_id,
            'status' => 'Approved',
            'version' => 1,
            'definition_json' => $finalDefinition->definition_json,
            'notes' => $finalDefinition->notes,
            'alignment_summary' => 'Legacy board wording was aligned in the prior close cycle.',
            'operational_status' => 'operationally_ready',
            'operational_status_notes' => 'Existing dashboard already uses the approved logic.',
            'validation_steps' => 'Prior official validation steps.',
            'common_mismatch_causes' => 'Prior official mismatch causes.',
            'implementation_gaps' => [
                'current_state' => 'Board pack uses the approved query.',
                'gap' => 'Billing feed still lands one day late.',
                'owner' => 'Revenue ops',
                'suggested_next_step' => 'Move the sync window forward before month-end close.',
            ],
            'owner_participant_id' => $orgOwner->id,
            'signed_off_at' => $finalDefinition->signed_off_at,
            'signed_off_by' => $finalDefinition->signed_off_by,
        ])
        : null;

    $proposal = ChangeProposal::factory()
        ->for($metric)
        ->approved(now()->subDay())
        ->create([
            'organization_id' => $organization->id,
            'engagement_id' => $engagement->id,
            'payload' => array_replace_recursive([
                'summary' => ['label' => 'Summary', 'value' => 'Adjusted for FY26 targets'],
                'owner' => ['label' => 'Owner', 'value' => 'Product Ops'],
                'notes' => 'Incorporates retention uplift assumptions.',
            ], $proposalPayload),
        ]);

    return [
        'consultant' => $consultant,
        'metric' => $metric,
        'final_definition' => $finalDefinition,
        'proposal' => $proposal,
        'prior_official' => $priorOfficial,
    ];
}

it('creates the next official org definition revision and clears stale consultant signals on merge', function () {
    [
        'consultant' => $consultant,
        'metric' => $metric,
        'final_definition' => $finalDefinition,
        'proposal' => $proposal,
        'prior_official' => $priorOfficial,
    ] = makeMergePromotionContext();

    $result = app(MergeChangeProposal::class)->handle($proposal, $consultant);

    $mergedDefinition = FinalDefinition::query()
        ->where('metric_id', $metric->getKey())
        ->orderByDesc('version')
        ->first();

    $mergedOfficial = OrgFinalDefinition::query()
        ->where('org_metric_id', $metric->org_metric_id)
        ->orderByDesc('version')
        ->first();

    expect($result['status'])->toBe(ChangeProposalStatus::MERGED->value)
        ->and($result['final_definition_version'])->toBe(2)
        ->and($mergedDefinition)->not->toBeNull()
        ->and($mergedDefinition?->version)->toBe(2)
        ->and($mergedDefinition?->owner_participant_id)->toBe($finalDefinition->owner_participant_id)
        ->and($mergedDefinition?->alignment_summary)->toBeNull()
        ->and($mergedDefinition?->operational_status)->toBeNull()
        ->and($mergedDefinition?->operational_status_notes)->toBeNull()
        ->and($mergedDefinition?->validation_steps)->toBeNull()
        ->and($mergedDefinition?->common_mismatch_causes)->toBeNull()
        ->and($mergedDefinition?->implementation_gaps)->toBeNull();

    expect($mergedOfficial)->not->toBeNull()
        ->and($mergedOfficial?->version)->toBe(2)
        ->and($mergedOfficial?->source_metric_id)->toBe($metric->id)
        ->and(data_get($mergedOfficial?->definition_json, 'summary.value'))->toBe('Adjusted for FY26 targets')
        ->and(data_get($mergedOfficial?->definition_json, 'owner.value'))->toBe('Product Ops')
        ->and($mergedOfficial?->notes)->toBe('Incorporates retention uplift assumptions.')
        ->and($mergedOfficial?->alignment_summary)->toBeNull()
        ->and($mergedOfficial?->operational_status)->toBeNull()
        ->and($mergedOfficial?->operational_status_notes)->toBeNull()
        ->and($mergedOfficial?->validation_steps)->toBeNull()
        ->and($mergedOfficial?->common_mismatch_causes)->toBeNull()
        ->and($mergedOfficial?->implementation_gaps)->toBe($priorOfficial?->implementation_gaps);

    $priorOfficial?->refresh();

    expect($priorOfficial)->not->toBeNull()
        ->and($priorOfficial?->alignment_summary)->toBe('Legacy board wording was aligned in the prior close cycle.')
        ->and($priorOfficial?->operational_status)->toBe('operationally_ready')
        ->and($priorOfficial?->operational_status_notes)->toBe('Existing dashboard already uses the approved logic.')
        ->and($priorOfficial?->validation_steps)->toBe('Prior official validation steps.')
        ->and($priorOfficial?->common_mismatch_causes)->toBe('Prior official mismatch causes.');
});

it('allows merge payloads to replace implementation gaps on the new official org revision', function () {
    [
        'consultant' => $consultant,
        'metric' => $metric,
        'proposal' => $proposal,
    ] = makeMergePromotionContext([
        'implementation_gaps' => [
            'current_state' => 'Finance workbook still has a manual adjustment step.',
            'gap' => 'The manual close adjustment is not reflected in BI yet.',
            'owner' => 'Finance systems',
            'suggested_next_step' => 'Automate the workbook adjustment before the next quarter close.',
        ],
    ]);

    app(MergeChangeProposal::class)->handle($proposal, $consultant);

    $mergedDefinition = FinalDefinition::query()
        ->where('metric_id', $metric->getKey())
        ->orderByDesc('version')
        ->first();

    $mergedOfficial = OrgFinalDefinition::query()
        ->where('org_metric_id', $metric->org_metric_id)
        ->orderByDesc('version')
        ->first();

    $expectedGaps = [
        'current_state' => 'Finance workbook still has a manual adjustment step.',
        'gap' => 'The manual close adjustment is not reflected in BI yet.',
        'owner' => 'Finance systems',
        'suggested_next_step' => 'Automate the workbook adjustment before the next quarter close.',
    ];

    expect($mergedDefinition)->not->toBeNull()
        ->and($mergedDefinition?->implementation_gaps)->toBe($expectedGaps);

    expect($mergedOfficial)->not->toBeNull()
        ->and($mergedOfficial?->implementation_gaps)->toBe($expectedGaps)
        ->and($mergedOfficial?->alignment_summary)->toBeNull()
        ->and($mergedOfficial?->operational_status)->toBeNull()
        ->and($mergedOfficial?->operational_status_notes)->toBeNull()
        ->and($mergedOfficial?->validation_steps)->toBeNull()
        ->and($mergedOfficial?->common_mismatch_causes)->toBeNull();
});

it('rolls back merge when no org owner can be resolved for the merged official revision', function () {
    [
        'consultant' => $consultant,
        'metric' => $metric,
        'proposal' => $proposal,
    ] = makeMergePromotionContext(withOrgOwner: false, withPriorOfficial: false);

    expect(fn () => app(MergeChangeProposal::class)->handle($proposal, $consultant))
        ->toThrow(ValidationException::class, 'Assign an org-level owner before merging changes.');

    $proposal->refresh();

    expect($proposal->status)->toBe(ChangeProposalStatus::APPROVED)
        ->and($proposal->merged_at)->toBeNull()
        ->and(
            FinalDefinition::query()
                ->where('metric_id', $metric->getKey())
                ->count()
        )->toBe(1)
        ->and(
            OrgFinalDefinition::query()
                ->where('org_metric_id', $metric->org_metric_id)
                ->count()
        )->toBe(0);
});
