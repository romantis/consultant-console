<?php

use App\Actions\Metrics\BuildMetricWorkshopReadiness;
use App\Models\Engagement;
use App\Models\Metric;
use App\Models\MetricDefinition;
use App\Models\MetricTemplate;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function readinessTeamDepartmentDefinition(Metric $metric, string $department, string $status = 'Submitted'): MetricDefinition
{
    return MetricDefinition::factory()->create([
        'metric_id' => $metric->id,
        'status' => $status,
        'definition_json' => [
            'team_department' => $department,
        ],
        'normalized_json' => [
            'fields' => [],
            'meta' => [
                'team_department' => [
                    'type' => 'text',
                    'label' => 'Respondent team or department',
                    'raw' => $department,
                    'normalized' => str($department)->trim()->lower()->toString(),
                ],
            ],
        ],
    ]);
}

it('returns an empty snapshot when the metric has no template required departments', function () {
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
    ]);
    $metric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
    ]);

    $snapshot = app(BuildMetricWorkshopReadiness::class)->handle($metric)->toArray();

    expect($snapshot)->toBe([
        'required_departments' => [],
        'represented_departments' => [],
        'missing_required_departments' => [],
        'gaps' => [],
    ]);
});

it('reports missing required department perspectives, deduping and normalizing labels', function () {
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
    ]);
    $template = MetricTemplate::factory()->create([
        'key' => 'cac_cpl',
        'form_schema_json' => [
            'required_departments' => [
                'Finance',
                'Marketing',
                'Sales',
                ' sales ',
            ],
            'fields' => [[
                'name' => 'team_department',
                'type' => 'text',
                'label' => 'Respondent team or department',
                'required' => true,
                'meta' => true,
                'classification' => [
                    'role' => 'context',
                    'exclude_from_consensus' => true,
                ],
            ]],
        ],
    ]);
    $metric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'metric_template_id' => $template->id,
    ]);

    readinessTeamDepartmentDefinition($metric, 'finance');
    readinessTeamDepartmentDefinition($metric, ' Marketing ');
    readinessTeamDepartmentDefinition($metric, 'Sales', status: 'Draft');

    $snapshot = app(BuildMetricWorkshopReadiness::class)->handle($metric)->toArray();

    // The duplicate ' sales ' collapses into the canonical 'Sales' label, and the
    // submitted finance/marketing responses are matched case-insensitively.
    expect($snapshot['required_departments'])->toBe(['Finance', 'Marketing', 'Sales']);
    expect($snapshot['represented_departments'])->toBe(['Finance', 'Marketing']);
    expect($snapshot['missing_required_departments'])->toBe(['Sales']);

    expect($snapshot['gaps'])->toHaveCount(1);
    expect($snapshot['gaps'][0])->toMatchArray([
        'id' => 'readiness:required_department:sales',
        'scope' => 'workshop_readiness',
        'kind' => 'missing_stakeholder_perspective',
        'label' => 'Sales perspective',
    ]);
    expect($snapshot['gaps'][0]['citation_ids'][0])
        ->toBe('metric_template:cac_cpl:required_department:sales');
});
