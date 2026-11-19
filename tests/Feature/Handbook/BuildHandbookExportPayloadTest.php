<?php

use App\Actions\Handbook\BuildHandbookExportPayload;
use App\Enums\EngagementStatus;
use App\Enums\MetricStatus;
use App\Models\Engagement;
use App\Models\FinalDefinition;
use App\Models\Metric;
use App\Models\Organization;
use App\Models\OrgFinalDefinition;
use App\Models\OrgMetricParticipant;
use App\Models\ReconciliationWorkshop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * @return array{metric: Metric, current_official: OrgFinalDefinition}
 */
function makeOfficialHandbookMetric(
    Organization $organization,
    Engagement $engagement,
    User $approver,
    string $name,
    bool $withHistory = false,
    array $currentSignals = []
): array {
    $metric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'status' => MetricStatus::FINALIZED,
        'name' => $name,
    ]);

    $orgOwner = OrgMetricParticipant::factory()->create([
        'org_metric_id' => $metric->org_metric_id,
        'user_id' => $approver->id,
        'responsibility' => 'owner',
    ]);

    FinalDefinition::factory()
        ->for($metric)
        ->withConsultantSignals([
            'alignment_summary' => 'Metric-level stale alignment summary',
            'implementation_gaps' => [
                'owner' => 'Finance ops',
                'suggested_next_step' => 'Retire the stale workbook flow.',
            ],
        ])
        ->create([
            'status' => 'Approved',
            'definition_json' => [
                'summary' => ['label' => 'Summary', 'value' => 'Metric-level stale summary'],
            ],
            'notes' => 'Metric-level stale note',
            'signed_off_at' => Carbon::parse('2026-03-01 09:00:00'),
            'signed_off_by' => $approver->id,
        ]);

    if ($withHistory) {
        OrgFinalDefinition::factory()
            ->withConsultantSignals([
                'alignment_summary' => 'Prior aligned story',
                'operational_status' => 'operationally_ready',
                'operational_status_notes' => 'Prior dashboard already matched.',
                'implementation_gaps' => [
                    'current_state' => 'Prior board pack query in place.',
                    'gap' => 'Manual file handoff remains.',
                    'owner' => 'Finance ops',
                    'suggested_next_step' => 'Automate the handoff.',
                ],
            ])
            ->create([
                'org_metric_id' => $metric->org_metric_id,
                'source_metric_id' => $metric->id,
                'source_metric_template_id' => $metric->metric_template_id,
                'status' => 'Approved',
                'version' => 1,
                'definition_json' => [
                    'summary' => ['label' => 'Summary', 'value' => 'Prior official wording'],
                ],
                'notes' => 'Prior official note',
                'owner_participant_id' => $orgOwner->id,
                'signed_off_at' => Carbon::parse('2026-02-28 11:00:00'),
                'signed_off_by' => $approver->id,
            ]);
    }

    $currentOfficial = OrgFinalDefinition::factory()
        ->withConsultantSignals(array_replace([
            'alignment_summary' => "{$name} official alignment summary",
            'operational_status' => 'needs_follow_up',
            'operational_status_notes' => "{$name} still needs a dashboard filter fix.",
            'validation_steps' => "{$name} workbook totals must match the approved board pack.",
            'common_mismatch_causes' => "{$name} can drift when filters include internal tenants.",
            'executive_takeaway' => "{$name} official executive takeaway",
            'implementation_gaps' => [
                'current_state' => "{$name} still reconciles in a workbook.",
                'gap' => "{$name} BI model still includes internal tenants.",
                'owner' => 'Revenue ops',
                'suggested_next_step' => "Update {$name} warehouse filters before the next close.",
            ],
        ], $currentSignals))
        ->create([
            'org_metric_id' => $metric->org_metric_id,
            'source_metric_id' => $metric->id,
            'source_metric_template_id' => $metric->metric_template_id,
            'status' => 'Approved',
            'version' => $withHistory ? 2 : 1,
            'definition_json' => [
                'summary' => ['label' => 'Summary', 'value' => "{$name} official wording"],
            ],
            'notes' => "{$name} official note",
            'owner_participant_id' => $orgOwner->id,
            'signed_off_at' => Carbon::parse('2026-03-05 15:00:00'),
            'signed_off_by' => $approver->id,
        ]);

    return [
        'metric' => $metric,
        'current_official' => $currentOfficial,
    ];
}

function makeHandbookTestOrganization(): Organization
{
    return Organization::query()->create([
        'name' => 'Handbook Test Org '.str()->upper(str()->random(4)),
        'slug' => 'handbook-test-org-'.str()->lower(str()->random(6)),
        'settings_json' => [
            'locale' => 'en',
            'currency' => 'USD',
            'timezone' => 'UTC',
        ],
    ]);
}

it('builds handbook payload from org-level official definitions with executive brief aggregates, history, approvals, and review context', function () {
    Carbon::setTestNow('2026-03-11 12:00:00');

    $organization = makeHandbookTestOrganization();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
        'status' => EngagementStatus::FINALIZED,
        'title' => 'Board Pack Alignment',
        'starts_on' => '2026-02-01',
        'ends_on' => '2026-02-20',
        'review_cadence' => 'Quarterly',
        'next_review_on' => '2026-06-30',
    ]);
    $approver = User::factory()->create(['name' => 'Avery Approver']);

    makeOfficialHandbookMetric($organization, $engagement, $approver, 'ARR', withHistory: true);
    makeOfficialHandbookMetric(
        $organization,
        $engagement,
        $approver,
        'Gross Margin',
        withHistory: false,
        currentSignals: [
            'operational_status' => 'operationally_ready',
            'operational_status_notes' => 'Gross Margin dashboard already matches the approved logic.',
            'implementation_gaps' => null,
        ]
    );

    $metrics = Metric::query()
        ->where('organization_id', $organization->id)
        ->where('status', MetricStatus::FINALIZED)
        ->get();

    $bundle = app(BuildHandbookExportPayload::class)->build(
        $organization,
        $metrics,
        $approver,
        Carbon::parse('2026-03-11 12:00:00')
    );

    $payload = $bundle['payload'];
    $approvalSignedOffAt = Carbon::parse('2026-03-05 15:00:00', config('app.timezone'))->toIso8601String();

    expect($payload['next_review_on'])->toBe('2026-06-30')
        ->and($payload['review_cadence'])->toBe('Quarterly')
        ->and($payload['executive_brief'])->toBe([
            'engagement_id' => (string) $engagement->getKey(),
            'engagement_title' => 'Board Pack Alignment',
            'starts_on' => '2026-02-01',
            'ends_on' => '2026-02-20',
            'total_metric_count' => 2,
            'approved_metric_count' => 2,
            'operational_ready_count' => 1,
            'needs_follow_up_count' => 1,
            'implementation_gap_count' => 1,
            'next_review_on' => '2026-06-30',
            'review_cadence' => 'Quarterly',
        ])
        ->and($payload['approvals'])->toHaveCount(2)
        ->and($payload['approvals'][0])->toBe([
            'metric_name' => 'ARR',
            'signed_off_at' => $approvalSignedOffAt,
            'signed_off_by_name' => 'Avery Approver',
        ]);

    $arrPayload = collect($payload['metrics'])->firstWhere('name', 'ARR');

    expect($arrPayload)->not->toBeNull()
        ->and($arrPayload['alignment_summary'])->toBe('ARR official alignment summary')
        ->and($arrPayload['operational_status'])->toBe('needs_follow_up')
        ->and($arrPayload['operational_status_notes'])->toBe('ARR still needs a dashboard filter fix.')
        ->and($arrPayload['validation_steps'])->toBe('ARR workbook totals must match the approved board pack.')
        ->and($arrPayload['common_mismatch_causes'])->toBe('ARR can drift when filters include internal tenants.')
        ->and($arrPayload['executive_takeaway'])->toBe('ARR official executive takeaway')
        ->and($arrPayload['implementation_gaps']['owner'])->toBe('Revenue ops')
        ->and($arrPayload['notes'])->toBe('ARR official note')
        ->and(collect($arrPayload['fields'])->firstWhere('key', 'summary')['value'] ?? null)->toBe('ARR official wording')
        ->and(collect($arrPayload['fields'])->firstWhere('key', 'validation_steps'))->toBeNull()
        ->and(collect($arrPayload['fields'])->firstWhere('key', 'common_mismatch_causes'))->toBeNull()
        ->and(collect($arrPayload['fields'])->firstWhere('key', 'summary')['value'] ?? null)->not->toBe('Metric-level stale summary')
        ->and($arrPayload['official_definition_history'])->toHaveCount(1)
        ->and($arrPayload['official_definition_history'][0]['version'])->toBe(1)
        ->and($arrPayload['official_definition_history'][0]['signed_off_by'])->toBe([
            'id' => $approver->id,
            'name' => 'Avery Approver',
        ])
        ->and($arrPayload['official_definition_history'][0]['metric']['engagement'])->toBe([
            'id' => $engagement->id,
            'title' => 'Board Pack Alignment',
        ]);

    Carbon::setTestNow();
});

it('omits handbook review summary when official metrics come from different engagements', function () {
    $organization = makeHandbookTestOrganization();
    $approver = User::factory()->create(['name' => 'Jordan Reviewer']);

    $engagementA = Engagement::factory()->create([
        'organization_id' => $organization->id,
        'status' => EngagementStatus::FINALIZED,
        'review_cadence' => 'Monthly',
        'next_review_on' => '2026-04-15',
    ]);

    $engagementB = Engagement::factory()->create([
        'organization_id' => $organization->id,
        'status' => EngagementStatus::FINALIZED,
        'review_cadence' => 'Quarterly',
        'next_review_on' => '2026-06-30',
    ]);

    makeOfficialHandbookMetric($organization, $engagementA, $approver, 'ARR');
    makeOfficialHandbookMetric($organization, $engagementB, $approver, 'Pipeline Coverage');

    $metrics = Metric::query()
        ->where('organization_id', $organization->id)
        ->where('status', MetricStatus::FINALIZED)
        ->get();

    $payload = app(BuildHandbookExportPayload::class)
        ->build($organization, $metrics)['payload'];

    expect($payload['next_review_on'])->toBeNull()
        ->and($payload['review_cadence'])->toBeNull()
        ->and($payload['executive_brief'])->toBe([
            'engagement_id' => null,
            'engagement_title' => null,
            'starts_on' => null,
            'ends_on' => null,
            'total_metric_count' => 2,
            'approved_metric_count' => 2,
            'operational_ready_count' => 0,
            'needs_follow_up_count' => 2,
            'implementation_gap_count' => 2,
            'next_review_on' => null,
            'review_cadence' => null,
        ])
        ->and($payload['approvals'])->toHaveCount(2);
});

it('adds captured workshop rationale to matching handbook fields and leaves other fields silent', function () {
    $organization = makeHandbookTestOrganization();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
        'status' => EngagementStatus::FINALIZED,
        'title' => 'Board Pack Alignment',
    ]);
    $approver = User::factory()->create(['name' => 'Avery Approver']);

    $fixture = makeOfficialHandbookMetric($organization, $engagement, $approver, 'ARR');

    $fixture['current_official']->forceFill([
        'definition_json' => [
            'summary' => ['label' => 'Summary', 'value' => 'ARR official wording'],
            'formula' => ['label' => 'Formula', 'value' => 'Starting ARR + expansion - churn'],
            'notes' => ['label' => 'Notes', 'value' => 'Board package wording'],
        ],
    ])->save();

    ReconciliationWorkshop::factory()->create([
        'metric_id' => $fixture['metric']->id,
        'engagement_id' => $engagement->id,
        'scheduled_at' => Carbon::parse('2026-03-04 11:00:00'),
        'decision_snapshot' => [
            'field_decisions' => [
                'summary' => [
                    'selected' => 'arr-board-package',
                    'rationale' => 'Finance and RevOps agreed on one board-facing summary line.',
                ],
                'formula' => [
                    'selected' => 'starting_arr_plus_expansion_minus_churn',
                    'rationale' => '   ',
                ],
            ],
        ],
    ]);

    $metrics = Metric::query()
        ->where('organization_id', $organization->id)
        ->where('status', MetricStatus::FINALIZED)
        ->get();

    $payload = app(BuildHandbookExportPayload::class)
        ->build($organization, $metrics)['payload'];

    $arrPayload = collect($payload['metrics'])->firstWhere('name', 'ARR');
    $fields = collect($arrPayload['fields'] ?? [])->keyBy('key');

    expect($fields->get('summary')['rationale'] ?? null)
        ->toBe('Finance and RevOps agreed on one board-facing summary line.')
        ->and(array_key_exists('rationale', $fields->get('formula')))->toBeFalse()
        ->and(array_key_exists('rationale', $fields->get('notes')))->toBeFalse();
});

it('changes handbook source checksum when executive brief metadata changes', function () {
    $organization = makeHandbookTestOrganization();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
        'status' => EngagementStatus::FINALIZED,
        'title' => 'Board Pack Alignment',
        'starts_on' => '2026-02-01',
        'review_cadence' => 'Quarterly',
        'next_review_on' => '2026-06-30',
    ]);
    $approver = User::factory()->create();

    makeOfficialHandbookMetric($organization, $engagement, $approver, 'ARR');

    $metrics = Metric::query()
        ->where('organization_id', $organization->id)
        ->where('status', MetricStatus::FINALIZED)
        ->get();

    $builder = app(BuildHandbookExportPayload::class);

    $before = $builder->sourceChecksum($organization, $metrics);

    $engagement->forceFill([
        'title' => 'Board Pack Alignment Updated',
        'starts_on' => '2026-02-03',
    ])->save();

    $after = $builder->sourceChecksum($organization, $metrics);

    expect($before)->not->toBe($after);
});

it('changes handbook source checksum when captured workshop rationale changes', function () {
    $organization = makeHandbookTestOrganization();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
        'status' => EngagementStatus::FINALIZED,
        'title' => 'Board Pack Alignment',
    ]);
    $approver = User::factory()->create();

    $fixture = makeOfficialHandbookMetric($organization, $engagement, $approver, 'ARR');

    ReconciliationWorkshop::factory()->create([
        'metric_id' => $fixture['metric']->id,
        'engagement_id' => $engagement->id,
        'scheduled_at' => Carbon::parse('2026-03-04 11:00:00'),
        'decision_snapshot' => [
            'field_decisions' => [
                'summary' => [
                    'selected' => 'arr-board-package',
                    'rationale' => 'Finance approved the board-facing summary wording.',
                ],
            ],
        ],
    ]);

    $metrics = Metric::query()
        ->where('organization_id', $organization->id)
        ->where('status', MetricStatus::FINALIZED)
        ->get();

    $builder = app(BuildHandbookExportPayload::class);

    $before = $builder->sourceChecksum($organization, $metrics);

    ReconciliationWorkshop::query()
        ->where('metric_id', $fixture['metric']->id)
        ->update([
            'decision_snapshot' => [
                'field_decisions' => [
                    'summary' => [
                        'selected' => 'arr-board-package',
                        'rationale' => 'Finance approved the summary after reconciling renewal timing.',
                    ],
                ],
            ],
        ]);

    $after = $builder->sourceChecksum($organization, $metrics);

    expect($before)->not->toBe($after);
});

it('builds handbook payload within a bounded query budget for history-heavy exports', function () {
    $organization = makeHandbookTestOrganization();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
        'status' => EngagementStatus::FINALIZED,
        'title' => 'Board Pack Alignment',
        'starts_on' => '2026-02-01',
        'ends_on' => '2026-02-20',
        'review_cadence' => 'Quarterly',
        'next_review_on' => '2026-06-30',
    ]);
    $approver = User::factory()->create(['name' => 'Taylor Approver']);

    foreach (range(1, 8) as $index) {
        makeOfficialHandbookMetric(
            $organization,
            $engagement,
            $approver,
            "Metric {$index}",
            withHistory: true
        );
    }

    $metrics = Metric::query()
        ->where('organization_id', $organization->id)
        ->where('status', MetricStatus::FINALIZED)
        ->get();

    DB::flushQueryLog();
    DB::enableQueryLog();

    $payload = app(BuildHandbookExportPayload::class)
        ->build($organization, $metrics)['payload'];

    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    $handbookQueryCount = countHandbookPayloadQueries($queries);

    expect($payload['metrics'])->toHaveCount(8)
        ->and($payload['executive_brief']['approved_metric_count'])->toBe(8)
        ->and($handbookQueryCount)->toBeLessThanOrEqual(8);
});

/**
 * @param  array<int, array{query:string, bindings:array<int, mixed>, time:float}>  $queries
 */
function countHandbookPayloadQueries(array $queries): int
{
    return collect($queries)
        ->pluck('query')
        ->filter(function (string $query): bool {
            $query = str_replace('"', '', strtolower($query));

            return str_contains($query, 'org_final_definitions')
                || str_contains($query, 'metrics')
                || str_contains($query, 'engagements')
                || str_contains($query, 'org_metric_participants')
                || str_contains($query, 'users');
        })
        ->count();
}
