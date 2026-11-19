<?php

use App\Actions\Handbook\BuildHandbookExportPayload;
use App\Enums\EngagementStatus;
use App\Enums\MembershipRole;
use App\Enums\MetricStatus;
use App\Models\AuditLog;
use App\Models\Engagement;
use App\Models\FinalDefinition;
use App\Models\Membership;
use App\Models\Metric;
use App\Models\Organization;
use App\Models\OrgFinalDefinition;
use App\Models\OrgMetric;
use App\Models\StaffOperatorAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

/**
 * @return array{
 *   organization: Organization,
 *   staff_user: User,
 *   org_admin_user: User,
 *   engagement: Engagement,
 *   metric: Metric,
 *   org_metric: OrgMetric,
 *   final_definition: FinalDefinition,
 *   prior_official: OrgFinalDefinition,
 *   current_official: OrgFinalDefinition
 * }
 */
function makeOrgMetricSignalsSetup(): array
{
    $organization = Organization::factory()->create();

    $staffUser = User::factory()->create();
    Membership::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $staffUser->id,
        'role' => MembershipRole::ADMIN,
    ]);

    $internalOrganization = Organization::factory()->create([
        'is_internal' => true,
    ]);
    Membership::factory()->create([
        'organization_id' => $internalOrganization->id,
        'user_id' => $staffUser->id,
        'role' => MembershipRole::ADMIN,
    ]);

    $orgAdminUser = User::factory()->create();
    Membership::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $orgAdminUser->id,
        'role' => MembershipRole::ADMIN,
    ]);

    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
        'status' => EngagementStatus::IN_WORKSHOP->value,
    ]);

    $metric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'status' => MetricStatus::FINALIZED,
    ]);

    $orgMetric = $metric->orgMetric()->firstOrFail();

    $finalDefinition = FinalDefinition::factory()->create([
        'metric_id' => $metric->id,
        'status' => 'Approved',
        'version' => 2,
        'signed_off_at' => now()->subDays(2),
        'signed_off_by' => $staffUser->id,
        'alignment_summary' => 'Locked metric-level alignment summary.',
        'operational_status' => 'operationally_ready',
        'operational_status_notes' => 'Locked final definition note.',
        'validation_steps' => 'Locked metric-level validation steps.',
        'common_mismatch_causes' => 'Locked metric-level mismatch causes.',
        'implementation_gaps' => [
            'current_state' => 'Metric-level signals were promoted at sign-off.',
            'gap' => 'Locked final definition should stay immutable.',
            'owner' => 'Finance ops',
            'suggested_next_step' => 'Use the org-level endpoint for updates.',
        ],
    ]);

    $priorOfficial = OrgFinalDefinition::factory()->create([
        'org_metric_id' => $orgMetric->id,
        'source_metric_id' => $metric->id,
        'source_metric_template_id' => $metric->metric_template_id,
        'status' => 'Approved',
        'version' => 1,
        'signed_off_at' => now()->subDays(3),
        'signed_off_by' => $staffUser->id,
        'alignment_summary' => 'Prior official alignment summary.',
        'operational_status' => 'needs_follow_up',
        'operational_status_notes' => 'Prior official operational note.',
        'validation_steps' => 'Prior official validation steps.',
        'common_mismatch_causes' => 'Prior official mismatch causes.',
        'implementation_gaps' => [
            'current_state' => 'Prior workbook still drives the board pack.',
            'gap' => 'Prior revision still needs manual reconciliation.',
            'owner' => 'Revenue ops',
            'suggested_next_step' => 'Retire the workbook flow.',
        ],
    ]);

    $currentOfficial = OrgFinalDefinition::factory()->create([
        'org_metric_id' => $orgMetric->id,
        'source_metric_id' => $metric->id,
        'source_metric_template_id' => $metric->metric_template_id,
        'status' => 'Approved',
        'version' => 2,
        'signed_off_at' => now()->subDay(),
        'signed_off_by' => $staffUser->id,
        'alignment_summary' => 'Current official alignment summary.',
        'operational_status' => 'operationally_ready',
        'operational_status_notes' => 'Current official operational note.',
        'validation_steps' => 'Current official validation steps.',
        'common_mismatch_causes' => 'Current official mismatch causes.',
        'implementation_gaps' => [
            'current_state' => 'Current official dashboard mostly matches the signed logic.',
            'gap' => 'One warehouse filter still needs adjustment.',
            'owner' => 'Finance systems',
            'suggested_next_step' => 'Update the warehouse filter before the next review.',
        ],
    ]);

    return [
        'organization' => $organization,
        'staff_user' => $staffUser,
        'org_admin_user' => $orgAdminUser,
        'engagement' => $engagement,
        'metric' => $metric,
        'org_metric' => $orgMetric,
        'final_definition' => $finalDefinition,
        'prior_official' => $priorOfficial,
        'current_official' => $currentOfficial,
    ];
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function orgMetricSignalsPayload(array $overrides = []): array
{
    return array_merge([
        'alignment_summary' => 'Finance and product aligned the official story for the next board pack.',
        'operational_status' => 'needs_follow_up',
        'operational_status_notes' => 'Warehouse filters still need one client-side update.',
        'validation_steps' => 'Compare workbook totals to the approved board pack.',
        'common_mismatch_causes' => 'Late backfills can drift from the signed source.',
        'implementation_gaps' => [
            'current_state' => 'The official definition is signed off, but the published dashboard lags behind.',
            'gap' => 'Internal tenants are still included in the board-pack rollup.',
            'owner' => 'Revenue ops',
            'suggested_next_step' => 'Patch the warehouse filter before the next board cycle.',
        ],
    ], $overrides);
}

function orgMetricWorkshopUrl(Organization $organization, Engagement $engagement, Metric $metric): string
{
    return route('org.engagements.workshop', [
        $organization,
        $engagement,
        'metric' => $metric->id,
    ]);
}

it('updates the latest signed-off org definition and exposes the new signals on the workshop payload', function () {
    $setup = makeOrgMetricSignalsSetup();
    $workshopUrl = orgMetricWorkshopUrl(
        $setup['organization'],
        $setup['engagement'],
        $setup['metric']
    );

    $this->actingAs($setup['staff_user'])
        ->from($workshopUrl)
        ->patch(
            route('org.org-metrics.signals.update', [
                $setup['organization'],
                $setup['org_metric'],
            ]),
            orgMetricSignalsPayload()
        )
        ->assertRedirect($workshopUrl);

    $setup['prior_official']->refresh();
    $setup['current_official']->refresh();

    expect($setup['prior_official']->alignment_summary)->toBe('Prior official alignment summary.')
        ->and($setup['prior_official']->operational_status)->toBe('needs_follow_up')
        ->and($setup['current_official']->alignment_summary)->toBe(
            'Finance and product aligned the official story for the next board pack.'
        )
        ->and($setup['current_official']->operational_status)->toBe('needs_follow_up')
        ->and($setup['current_official']->operational_status_notes)->toBe(
            'Warehouse filters still need one client-side update.'
        )
        ->and($setup['current_official']->validation_steps)->toBe(
            'Compare workbook totals to the approved board pack.'
        )
        ->and($setup['current_official']->common_mismatch_causes)->toBe(
            'Late backfills can drift from the signed source.'
        )
        ->and($setup['current_official']->implementation_gaps)->toBe([
            'current_state' => 'The official definition is signed off, but the published dashboard lags behind.',
            'gap' => 'Internal tenants are still included in the board-pack rollup.',
            'owner' => 'Revenue ops',
            'suggested_next_step' => 'Patch the warehouse filter before the next board cycle.',
        ]);

    $handbookPayload = app(BuildHandbookExportPayload::class)->build(
        $setup['organization'],
        Metric::query()->whereKey($setup['metric']->getKey())->get()
    )['payload'];
    $handbookMetric = collect($handbookPayload['metrics'])->firstWhere(
        'id',
        (string) $setup['metric']->getKey()
    );

    expect($handbookMetric)->not->toBeNull()
        ->and($handbookMetric['alignment_summary'])->toBe(
            'Finance and product aligned the official story for the next board pack.'
        )
        ->and($handbookMetric['operational_status'])->toBe('needs_follow_up')
        ->and($handbookMetric['operational_status_notes'])->toBe(
            'Warehouse filters still need one client-side update.'
        )
        ->and($handbookMetric['validation_steps'])->toBe(
            'Compare workbook totals to the approved board pack.'
        )
        ->and($handbookMetric['common_mismatch_causes'])->toBe(
            'Late backfills can drift from the signed source.'
        )
        ->and($handbookMetric['implementation_gaps']['owner'] ?? null)->toBe('Revenue ops');

    $auditLog = AuditLog::query()
        ->where('action', 'org-metric.signals.updated')
        ->where('subject_id', $setup['current_official']->id)
        ->latest()
        ->first();

    expect($auditLog)->not->toBeNull()
        ->and($auditLog?->properties['updated_fields'] ?? [])->toBe([
            'alignment_summary',
            'operational_status',
            'operational_status_notes',
            'implementation_gaps',
            'validation_steps',
            'common_mismatch_causes',
        ]);

    $this->actingAs($setup['staff_user'])
        ->get($workshopUrl)
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Workshops/MetricWorkshop')
            ->where('officialSignals.id', (string) $setup['current_official']->id)
            ->where('officialSignals.source', 'org_final_definition')
            ->where(
                'officialSignals.alignment_summary',
                'Finance and product aligned the official story for the next board pack.'
            )
            ->where('officialSignals.operational_status', 'needs_follow_up')
            ->where(
                'officialSignals.operational_status_notes',
                'Warehouse filters still need one client-side update.'
            )
            ->where(
                'officialSignals.validation_steps',
                'Compare workbook totals to the approved board pack.'
            )
            ->where(
                'officialSignals.common_mismatch_causes',
                'Late backfills can drift from the signed source.'
            )
            ->where('officialSignals.implementation_gaps.owner', 'Revenue ops')
        );
});

it('keeps the signed-off final definition locked and only allows post-sign-off signal edits through the org endpoint', function () {
    $setup = makeOrgMetricSignalsSetup();
    $workshopUrl = orgMetricWorkshopUrl(
        $setup['organization'],
        $setup['engagement'],
        $setup['metric']
    );

    $this->actingAs($setup['staff_user'])
        ->put(
            route('org.metrics.final-definition.update', [
                $setup['organization'],
                $setup['metric'],
            ]),
            [
                'definition' => [
                    ['field' => 'summary', 'label' => 'Summary', 'value' => 'Still locked after sign-off'],
                ],
                'operational_status' => 'needs_follow_up',
                'operational_status_notes' => 'This should stay locked on FinalDefinition.',
            ]
        )
        ->assertForbidden();

    $this->actingAs($setup['staff_user'])
        ->from($workshopUrl)
        ->patch(
            route('org.org-metrics.signals.update', [
                $setup['organization'],
                $setup['org_metric'],
            ]),
            [
                'operational_status' => 'needs_follow_up',
                'operational_status_notes' => 'Updated through the org signals endpoint.',
            ]
        )
        ->assertRedirect($workshopUrl);

    $setup['final_definition']->refresh();
    $setup['current_official']->refresh();

    expect($setup['final_definition']->operational_status)->toBe('operationally_ready')
        ->and($setup['final_definition']->operational_status_notes)->toBe('Locked final definition note.')
        ->and($setup['current_official']->operational_status)->toBe('needs_follow_up')
        ->and($setup['current_official']->operational_status_notes)->toBe(
            'Updated through the org signals endpoint.'
        );
});

it('forbids non-staff users from updating org metric signals', function () {
    $setup = makeOrgMetricSignalsSetup();

    $this->actingAs($setup['org_admin_user'])
        ->patch(
            route('org.org-metrics.signals.update', [
                $setup['organization'],
                $setup['org_metric'],
            ]),
            orgMetricSignalsPayload()
        )
        ->assertForbidden();
});

it('validates operational status on the org metric signals endpoint', function () {
    $setup = makeOrgMetricSignalsSetup();

    $response = $this->actingAs($setup['staff_user'])
        ->patchJson(
            route('org.org-metrics.signals.update', [
                $setup['organization'],
                $setup['org_metric'],
            ]),
            orgMetricSignalsPayload([
                'operational_status' => 'not-valid',
            ])
        );

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['operational_status']);

    $setup['current_official']->refresh();

    expect($setup['current_official']->operational_status)->toBe('operationally_ready');
});

it('allows assigned operators to update signals inside their delegated organization', function () {
    $setup = makeOrgMetricSignalsSetup();

    $operator = User::factory()->create();
    $internalOrg = Organization::query()->where('is_internal', true)->firstOrFail();
    Membership::factory()->create([
        'organization_id' => $internalOrg->id,
        'user_id' => $operator->id,
        'role' => MembershipRole::MEMBER,
    ]);

    StaffOperatorAssignment::factory()->create([
        'organization_id' => $setup['organization']->id,
        'user_id' => $operator->id,
    ]);

    $workshopUrl = orgMetricWorkshopUrl(
        $setup['organization'],
        $setup['engagement'],
        $setup['metric']
    );

    $this->actingAs($operator)
        ->from($workshopUrl)
        ->patch(
            route('org.org-metrics.signals.update', [
                $setup['organization'],
                $setup['org_metric'],
            ]),
            orgMetricSignalsPayload()
        )
        ->assertRedirect($workshopUrl);
});

it('denies operators outside their delegated organization from updating signals', function () {
    $setup = makeOrgMetricSignalsSetup();

    $operator = User::factory()->create();
    $internalOrg = Organization::query()->where('is_internal', true)->firstOrFail();
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

    $this->actingAs($operator)
        ->patch(
            route('org.org-metrics.signals.update', [
                $setup['organization'],
                $setup['org_metric'],
            ]),
            orgMetricSignalsPayload()
        )
        ->assertForbidden();
});
