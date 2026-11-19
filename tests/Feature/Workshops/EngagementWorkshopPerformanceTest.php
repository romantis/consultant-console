<?php

use App\Enums\EngagementStatus;
use App\Enums\EngagementWorkshopSessionStatus;
use App\Models\Engagement;
use App\Models\EngagementParticipant;
use App\Models\EngagementWorkshopSession;
use App\Models\EngagementWorkshopSessionMetric;
use App\Models\EngagementWorkshopSessionParticipant;
use App\Models\Membership;
use App\Models\Metric;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('lists workshop sessions without n+1 scoped session queries', function () {
    [$organization, $engagement, $staffMembership] = workshopPerformanceContext();
    [$metric, $participant] = workshopPerformanceScopeFixture($organization, $engagement);
    $otherEngagement = Engagement::factory()->create(['organization_id' => $organization->id]);
    [$otherMetric, $otherParticipant] = workshopPerformanceScopeFixture($organization, $otherEngagement);

    createWorkshopPerformanceSessions($engagement, $staffMembership, $metric, $participant, 24);
    createWorkshopPerformanceSessions(
        $otherEngagement,
        $staffMembership,
        $otherMetric,
        $otherParticipant,
        12
    );

    DB::flushQueryLog();
    DB::enableQueryLog();

    $response = $this->actingAs($staffMembership->user)->getJson(
        route('org.engagements.workshop-sessions.index', [$organization, $engagement])
    );

    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    $response->assertOk()->assertJsonCount(24, 'data');

    $sessionListQueryCount = countWorkshopQueries(
        $queries,
        [
            'engagement_workshop_sessions',
            'engagement_workshop_session_metrics',
            'engagement_workshop_session_participants',
        ]
    );

    expect($sessionListQueryCount)->toBeLessThan(4);
});

it('checks workshop gate with a bounded workshop-session query count', function () {
    [$organization, $engagement, $staffMembership] = workshopPerformanceContext(EngagementStatus::IN_REVIEW);

    EngagementWorkshopSession::factory()->count(40)->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'facilitator_membership_id' => $staffMembership->id,
        'status' => EngagementWorkshopSessionStatus::SCHEDULED,
        'starts_at' => now()->subHours(2),
        'ends_at' => now()->subHour(),
    ]);

    EngagementWorkshopSession::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'facilitator_membership_id' => $staffMembership->id,
        'status' => EngagementWorkshopSessionStatus::SCHEDULED,
        'starts_at' => now()->addHour(),
        'ends_at' => now()->addHours(2),
    ]);

    DB::flushQueryLog();
    DB::enableQueryLog();

    $response = $this->actingAs($staffMembership->user)->patch(
        route('org.engagements.status.update', [$organization, $engagement]),
        ['status' => EngagementStatus::IN_WORKSHOP->value]
    );

    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    $response->assertRedirect();

    $gateQueryCount = countWorkshopQueries($queries, ['engagement_workshop_sessions']);

    expect($engagement->refresh()->status)->toBe(EngagementStatus::IN_WORKSHOP)
        ->and($gateQueryCount)->toBeLessThan(2);
});

/**
 * @return array{Organization, Engagement, Membership}
 */
function workshopPerformanceContext(
    EngagementStatus $status = EngagementStatus::AWAITING_FORMS
): array {
    $organization = Organization::factory()->create();
    $internal = makeInternalOrg();
    $staffMembership = Membership::factory()->admin()->create([
        'organization_id' => $organization->id,
    ]);
    Membership::factory()->admin()->create([
        'organization_id' => $internal->id,
        'user_id' => $staffMembership->user_id,
    ]);
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
        'status' => $status->value,
    ]);

    return [$organization, $engagement, $staffMembership];
}

/**
 * @return array{Metric, EngagementParticipant}
 */
function workshopPerformanceScopeFixture(Organization $organization, Engagement $engagement): array
{
    $metric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
    ]);
    $participantMembership = Membership::factory()->member()->create([
        'organization_id' => $organization->id,
    ]);
    $participant = EngagementParticipant::factory()->create([
        'engagement_id' => $engagement->id,
        'membership_id' => $participantMembership->id,
    ]);

    return [$metric, $participant];
}

function createWorkshopPerformanceSessions(
    Engagement $engagement,
    Membership $staffMembership,
    Metric $metric,
    EngagementParticipant $participant,
    int $count
): void {
    EngagementWorkshopSession::factory()->count($count)->create([
        'organization_id' => $engagement->organization_id,
        'engagement_id' => $engagement->id,
        'facilitator_membership_id' => $staffMembership->id,
        'status' => EngagementWorkshopSessionStatus::SCHEDULED,
        'starts_at' => now()->addHours(2),
        'ends_at' => now()->addHours(3),
    ])->each(function (EngagementWorkshopSession $session) use ($metric, $participant): void {
        EngagementWorkshopSessionMetric::query()->create([
            'session_id' => $session->id,
            'metric_id' => $metric->id,
        ]);
        EngagementWorkshopSessionParticipant::query()->create([
            'session_id' => $session->id,
            'engagement_participant_id' => $participant->id,
        ]);
    });
}

/**
 * @param  array<int, array<string, mixed>>  $queries
 * @param  array<int, string>  $tableNames
 */
function countWorkshopQueries(array $queries, array $tableNames): int
{
    return collect($queries)
        ->pluck('query')
        ->filter(function (mixed $query) use ($tableNames): bool {
            $normalized = strtolower((string) $query);

            return collect($tableNames)
                ->contains(fn (string $table): bool => str_contains($normalized, strtolower($table)));
        })
        ->count();
}
