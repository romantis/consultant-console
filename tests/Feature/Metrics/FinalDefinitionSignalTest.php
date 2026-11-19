<?php

use App\Enums\MembershipRole;
use App\Models\Engagement;
use App\Models\FinalDefinition;
use App\Models\Membership;
use App\Models\Metric;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

/**
 * @return array{
 *   staff_user: User,
 *   organization: Organization,
 *   engagement: Engagement,
 *   metric: Metric,
 *   final_definition: FinalDefinition
 * }
 */
function makeFinalDefinitionSignalSetup(string $definitionStatus = 'Draft'): array
{
    $staffUser = User::factory()->create();
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
    ]);

    Membership::factory()
        ->for($organization)
        ->for($staffUser, 'user')
        ->role(MembershipRole::ADMIN)
        ->create();

    $internalOrganization = Organization::factory()->create([
        'is_internal' => true,
    ]);

    Membership::factory()
        ->for($internalOrganization, 'organization')
        ->for($staffUser, 'user')
        ->role(MembershipRole::ADMIN)
        ->create();

    $metric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
    ]);

    $finalDefinition = FinalDefinition::factory()->create([
        'metric_id' => $metric->id,
        'status' => $definitionStatus,
        'definition_json' => [
            'summary' => ['label' => 'Summary', 'value' => 'Original aligned summary'],
        ],
        'notes' => 'Original final definition notes.',
    ]);

    return [
        'staff_user' => $staffUser,
        'organization' => $organization,
        'engagement' => $engagement,
        'metric' => $metric,
        'final_definition' => $finalDefinition,
    ];
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function finalDefinitionUpdatePayload(array $overrides = []): array
{
    return array_merge([
        'definition' => [
            ['field' => 'summary', 'label' => 'Summary', 'value' => 'Updated aligned summary'],
        ],
        'notes' => 'Updated final definition notes.',
    ], $overrides);
}

function workshopUrl(Organization $organization, Engagement $engagement, Metric $metric): string
{
    return route('org.engagements.workshop', [
        $organization,
        $engagement,
        'metric' => $metric->id,
    ]);
}

it('persists consultant signal fields through the pre-sign-off endpoint and workshop payload', function () {
    $setup = makeFinalDefinitionSignalSetup();
    $workshopUrl = workshopUrl($setup['organization'], $setup['engagement'], $setup['metric']);

    $this->actingAs($setup['staff_user'])
        ->from($workshopUrl)
        ->put(
            route('org.metrics.final-definition.update', [$setup['organization'], $setup['metric']]),
            finalDefinitionUpdatePayload([
                'definition' => [
                    ['field' => 'summary', 'label' => 'Summary', 'value' => 'Updated aligned summary'],
                    ['field' => 'validation_steps', 'label' => 'Validation Steps', 'value' => 'Legacy row should not be saved.'],
                    ['field' => 'common_mismatch_causes', 'label' => 'Common Mismatch Causes', 'value' => 'Legacy row should not be saved.'],
                ],
                'alignment_summary' => 'Finance and product aligned on one contracted ARR narrative.',
                'operational_status' => 'needs_follow_up',
                'operational_status_notes' => 'Warehouse filters still include internal tenants.',
                'validation_steps' => 'Compare workbook totals to the board pack.',
                'common_mismatch_causes' => 'Internal tenants and late backfills cause drift.',
                'implementation_gaps' => [
                    'current_state' => 'Dashboard logic still includes internal tenants.',
                    'gap' => 'Board pack ARR needs a client-side filter adjustment.',
                    'owner' => 'Revenue ops',
                    'suggested_next_step' => 'Update the warehouse filter before the next board pack.',
                ],
            ])
        )
        ->assertRedirect($workshopUrl);

    $setup['final_definition']->refresh();

    expect($setup['final_definition']->alignment_summary)
        ->toBe('Finance and product aligned on one contracted ARR narrative.')
        ->and($setup['final_definition']->operational_status)->toBe('needs_follow_up')
        ->and($setup['final_definition']->operational_status_notes)->toBe('Warehouse filters still include internal tenants.')
        ->and($setup['final_definition']->validation_steps)->toBe('Compare workbook totals to the board pack.')
        ->and($setup['final_definition']->common_mismatch_causes)->toBe('Internal tenants and late backfills cause drift.')
        ->and(data_get($setup['final_definition']->definition_json, 'summary.value'))->toBe('Updated aligned summary')
        ->and(array_key_exists('validation_steps', $setup['final_definition']->definition_json))->toBeFalse()
        ->and(array_key_exists('common_mismatch_causes', $setup['final_definition']->definition_json))->toBeFalse()
        ->and($setup['final_definition']->implementation_gaps)->toBe([
            'current_state' => 'Dashboard logic still includes internal tenants.',
            'gap' => 'Board pack ARR needs a client-side filter adjustment.',
            'owner' => 'Revenue ops',
            'suggested_next_step' => 'Update the warehouse filter before the next board pack.',
        ]);

    $this->actingAs($setup['staff_user'])
        ->get($workshopUrl)
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Workshops/MetricWorkshop')
            ->where('finalDefinition.alignment_summary', 'Finance and product aligned on one contracted ARR narrative.')
            ->where('finalDefinition.operational_status', 'needs_follow_up')
            ->where('finalDefinition.operational_status_notes', 'Warehouse filters still include internal tenants.')
            ->where('finalDefinition.validation_steps', 'Compare workbook totals to the board pack.')
            ->where('finalDefinition.common_mismatch_causes', 'Internal tenants and late backfills cause drift.')
            ->where('finalDefinition.implementation_gaps.owner', 'Revenue ops')
            ->where(
                'finalDefinition.implementation_gaps.suggested_next_step',
                'Update the warehouse filter before the next board pack.'
            )
        );
});

it('saves alignment summary and implementation gaps on draft and pending approval definitions', function (string $status) {
    $setup = makeFinalDefinitionSignalSetup($status);
    $workshopUrl = workshopUrl($setup['organization'], $setup['engagement'], $setup['metric']);

    $this->actingAs($setup['staff_user'])
        ->from($workshopUrl)
        ->put(
            route('org.metrics.final-definition.update', [$setup['organization'], $setup['metric']]),
            finalDefinitionUpdatePayload([
                'alignment_summary' => "Consultant summary for {$status}.",
                'validation_steps' => "Validation steps for {$status}.",
                'common_mismatch_causes' => "Mismatch causes for {$status}.",
                'implementation_gaps' => [
                    'current_state' => 'Workbook reconciliation is still manual.',
                    'gap' => 'Definition is not automated in the board-pack source query.',
                    'owner' => 'Finance systems',
                    'next_step' => 'Promote the approved query before the next review.',
                ],
            ])
        )
        ->assertRedirect($workshopUrl);

    $setup['final_definition']->refresh();

    expect($setup['final_definition']->status)->toBe($status)
        ->and($setup['final_definition']->alignment_summary)->toBe("Consultant summary for {$status}.")
        ->and($setup['final_definition']->validation_steps)->toBe("Validation steps for {$status}.")
        ->and($setup['final_definition']->common_mismatch_causes)->toBe("Mismatch causes for {$status}.")
        ->and($setup['final_definition']->implementation_gaps)->toBe([
            'current_state' => 'Workbook reconciliation is still manual.',
            'gap' => 'Definition is not automated in the board-pack source query.',
            'owner' => 'Finance systems',
            'suggested_next_step' => 'Promote the approved query before the next review.',
        ]);
})->with(['Draft', 'PendingApproval']);

it('preserves existing consultant signals when the update payload omits them', function () {
    $setup = makeFinalDefinitionSignalSetup();
    $workshopUrl = workshopUrl($setup['organization'], $setup['engagement'], $setup['metric']);

    $setup['final_definition']->forceFill([
        'alignment_summary' => 'Existing consultant narrative.',
        'operational_status' => 'operationally_ready',
        'operational_status_notes' => 'Existing operational note.',
        'validation_steps' => 'Existing validation steps.',
        'common_mismatch_causes' => 'Existing mismatch causes.',
        'implementation_gaps' => [
            'current_state' => 'Existing dashboard already matches the definition.',
            'gap' => 'No remaining implementation gap.',
            'owner' => 'Finance ops',
            'suggested_next_step' => 'Review again next quarter.',
        ],
    ])->save();

    $this->actingAs($setup['staff_user'])
        ->from($workshopUrl)
        ->put(
            route('org.metrics.final-definition.update', [$setup['organization'], $setup['metric']]),
            finalDefinitionUpdatePayload()
        )
        ->assertRedirect($workshopUrl);

    $setup['final_definition']->refresh();

    expect($setup['final_definition']->alignment_summary)->toBe('Existing consultant narrative.')
        ->and($setup['final_definition']->operational_status)->toBe('operationally_ready')
        ->and($setup['final_definition']->operational_status_notes)->toBe('Existing operational note.')
        ->and($setup['final_definition']->validation_steps)->toBe('Existing validation steps.')
        ->and($setup['final_definition']->common_mismatch_causes)->toBe('Existing mismatch causes.')
        ->and($setup['final_definition']->implementation_gaps)->toBe([
            'current_state' => 'Existing dashboard already matches the definition.',
            'gap' => 'No remaining implementation gap.',
            'owner' => 'Finance ops',
            'suggested_next_step' => 'Review again next quarter.',
        ]);
});

it('rejects invalid operational status values on the pre-sign-off endpoint', function () {
    $setup = makeFinalDefinitionSignalSetup();

    $response = $this->actingAs($setup['staff_user'])
        ->putJson(
            route('org.metrics.final-definition.update', [$setup['organization'], $setup['metric']]),
            finalDefinitionUpdatePayload([
                'alignment_summary' => 'This update should be rejected.',
                'operational_status' => 'not-a-valid-status',
            ])
        );

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['operational_status']);

    $setup['final_definition']->refresh();

    expect($setup['final_definition']->alignment_summary)->toBeNull()
        ->and($setup['final_definition']->operational_status)->toBeNull();
});
