<?php

use App\Actions\Audit\LogOrgMetricEvent;
use App\Actions\Metrics\ApproveSignOff;
use App\Enums\EngagementStatus;
use App\Enums\MetricStatus;
use App\Jobs\NotifyOrgMetricSignOff;
use App\Models\Engagement;
use App\Models\FinalDefinition;
use App\Models\Metric;
use App\Models\MetricParticipant;
use App\Models\MetricTemplate;
use App\Models\OrgFinalDefinition;
use App\Models\OrgMetric;
use App\Models\OrgMetricParticipant;
use App\Models\User;
use App\Notifications\OrgMetricSignOffApproved;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

/**
 * @return array{0: Metric, 1: FinalDefinition, 2: MetricParticipant, 3: User, 4: OrgMetric}
 */
function makeOrgMetricSignOffSetup(?EngagementStatus $engagementStatus = null): array
{
    $orgMetric = OrgMetric::factory()->create();
    $organization = $orgMetric->organization;
    $engagement = Engagement::factory()->for($organization)->create([
        'status' => ($engagementStatus ?? EngagementStatus::DRAFT)->value,
    ]);

    $template = MetricTemplate::factory()->create([
        'canonical_metric_id' => $orgMetric->canonical_metric_id,
        'key' => $orgMetric->key,
        'name' => $orgMetric->name,
    ]);

    $metric = Metric::factory()
        ->for($organization)
        ->for($engagement)
        ->create([
            'org_metric_id' => $orgMetric->id,
            'metric_template_id' => $template->id,
            'key' => $orgMetric->key,
            'name' => $orgMetric->name,
            'status' => MetricStatus::IN_REVIEW,
        ]);

    $ownerUser = User::factory()->create();
    $ownerParticipant = MetricParticipant::factory()
        ->for($metric)
        ->for($ownerUser, 'user')
        ->approver()
        ->create();

    $approverUser = User::factory()->create();
    $approverParticipant = MetricParticipant::factory()
        ->for($metric)
        ->for($approverUser, 'user')
        ->approver()
        ->create();

    OrgMetricParticipant::factory()->create([
        'org_metric_id' => $orgMetric->id,
        'user_id' => $ownerUser->id,
        'responsibility' => 'owner',
    ]);

    OrgMetricParticipant::factory()->create([
        'org_metric_id' => $orgMetric->id,
        'user_id' => $approverUser->id,
        'responsibility' => 'approver',
    ]);

    $finalDefinition = FinalDefinition::factory()->create([
        'metric_id' => $metric->id,
        'status' => 'Draft',
        'definition_json' => [
            'summary' => ['label' => 'Summary', 'value' => 'Aligned'],
            'owner' => ['label' => 'Owner', 'value' => 'Finance'],
            'value' => ['label' => 'Metric Value', 'value' => '42'],
        ],
        'owner_participant_id' => $ownerParticipant->id,
    ]);

    return [$metric, $finalDefinition, $approverParticipant, $approverUser, $orgMetric];
}

it('approves org-level sign-off and mirrors the engagement state', function () {
    [$metric, $finalDefinition, $approverParticipant, $approverUser, $orgMetric] = makeOrgMetricSignOffSetup();

    $result = app(ApproveSignOff::class)->handle($metric, [
        'decision' => 'approve',
        'comment' => 'Looks good.',
    ], $approverUser);

    $metric->refresh();
    $finalDefinition->refresh();

    $orgDefinition = OrgFinalDefinition::query()
        ->where('org_metric_id', $orgMetric->id)
        ->where('source_metric_id', $metric->id)
        ->orderByDesc('version')
        ->first();

    expect($result['decision'])->toBe('approve')
        ->and($metric->status)->toBe(MetricStatus::FINALIZED)
        ->and($finalDefinition->status)->toBe('Approved')
        ->and($finalDefinition->signed_off_at)->not->toBeNull()
        ->and($orgDefinition?->status)->toBe('Approved')
        ->and($orgDefinition?->signed_off_by)->toBe($approverUser->id);
});

it('promotes consultant signal fields to the official org definition when sign-off is approved', function () {
    [$metric, $finalDefinition, $approverParticipant, $approverUser, $orgMetric] = makeOrgMetricSignOffSetup();

    $finalDefinition->forceFill([
        'alignment_summary' => 'Finance and product aligned on contracted ARR as the board-facing label.',
        'operational_status' => 'needs_follow_up',
        'operational_status_notes' => 'Dashboard filter still needs to exclude internal test accounts.',
        'validation_steps' => 'Compare workbook totals to the approved board pack.',
        'common_mismatch_causes' => 'Internal test accounts can drift into the rollup.',
        'implementation_gaps' => [
            'current_state' => 'Dashboard includes internal tenants.',
            'gap' => 'Internal accounts are still counted in ARR rollup.',
            'owner' => 'Revenue ops',
            'suggested_next_step' => 'Update the warehouse filter before the next board pack.',
        ],
    ])->save();

    app(ApproveSignOff::class)->handle($metric, [
        'decision' => 'approve',
        'comment' => 'Looks good.',
    ], $approverUser);

    $orgDefinition = OrgFinalDefinition::query()
        ->where('org_metric_id', $orgMetric->id)
        ->where('source_metric_id', $metric->id)
        ->orderByDesc('version')
        ->first();

    expect($orgDefinition)->not->toBeNull()
        ->and($orgDefinition?->alignment_summary)->toBe('Finance and product aligned on contracted ARR as the board-facing label.')
        ->and($orgDefinition?->operational_status)->toBe('needs_follow_up')
        ->and($orgDefinition?->operational_status_notes)->toBe('Dashboard filter still needs to exclude internal test accounts.')
        ->and($orgDefinition?->validation_steps)->toBe('Compare workbook totals to the approved board pack.')
        ->and($orgDefinition?->common_mismatch_causes)->toBe('Internal test accounts can drift into the rollup.')
        ->and($orgDefinition?->implementation_gaps)->toBe([
            'current_state' => 'Dashboard includes internal tenants.',
            'gap' => 'Internal accounts are still counted in ARR rollup.',
            'owner' => 'Revenue ops',
            'suggested_next_step' => 'Update the warehouse filter before the next board pack.',
        ]);
});

it('auto-advances engagement to finalized when all metrics are signed off', function () {
    [$metric, $finalDefinition, $approverParticipant, $approverUser] = makeOrgMetricSignOffSetup(
        EngagementStatus::IN_WORKSHOP
    );

    app(ApproveSignOff::class)->handle($metric, [
        'decision' => 'approve',
        'comment' => null,
    ], $approverUser);

    $engagement = Engagement::query()->find($metric->engagement_id);

    expect($engagement)->not->toBeNull()
        ->and($engagement->status)->toBe(EngagementStatus::FINALIZED)
        ->and($engagement->finalized_at)->not->toBeNull();
});

it('does not auto-advance when another metric is still in workshop', function () {
    [$metric, $finalDefinition, $approverParticipant, $approverUser] = makeOrgMetricSignOffSetup(
        EngagementStatus::IN_WORKSHOP
    );

    Metric::factory()
        ->for($metric->engagement)
        ->for($metric->organization)
        ->create([
            'status' => MetricStatus::IN_WORKSHOP,
        ]);

    app(ApproveSignOff::class)->handle($metric, [
        'decision' => 'approve',
        'comment' => null,
    ], $approverUser);

    $engagement = Engagement::query()->find($metric->engagement_id);

    expect($engagement)->not->toBeNull()
        ->and($engagement->status)->toBe(EngagementStatus::IN_WORKSHOP)
        ->and($engagement->finalized_at)->toBeNull();
});

it('notifies the org metric owner when sign-off is approved by another user', function () {
    Notification::fake();

    [$metric, $finalDefinition, $approverParticipant, $approverUser, $orgMetric] = makeOrgMetricSignOffSetup();

    $ownerParticipant = OrgMetricParticipant::query()
        ->with('user')
        ->where('org_metric_id', $orgMetric->id)
        ->where('responsibility', 'owner')
        ->first();
    $ownerUser = $ownerParticipant?->user;

    expect($ownerUser)->not->toBeNull();

    $job = new NotifyOrgMetricSignOff(
        (string) $orgMetric->getKey(),
        (string) $metric->getKey(),
        (string) $metric->organization_id,
        (string) $metric->engagement_id,
        NotifyOrgMetricSignOff::EVENT_APPROVED,
        (string) $approverUser->getKey()
    );

    $job->handle(app(LogOrgMetricEvent::class));

    Notification::assertSentTo($ownerUser, OrgMetricSignOffApproved::class);
    Notification::assertNotSentTo($approverUser, OrgMetricSignOffApproved::class);
});

it('skips the org sign-off email when the approver is the owner', function () {
    Notification::fake();

    [$metric, $finalDefinition, $approverParticipant, $approverUser, $orgMetric] = makeOrgMetricSignOffSetup();

    $ownerParticipant = OrgMetricParticipant::query()
        ->where('org_metric_id', $orgMetric->id)
        ->where('responsibility', 'owner')
        ->firstOrFail();

    $ownerParticipant->forceFill([
        'user_id' => $approverUser->id,
    ])->save();

    $job = new NotifyOrgMetricSignOff(
        (string) $orgMetric->getKey(),
        (string) $metric->getKey(),
        (string) $metric->organization_id,
        (string) $metric->engagement_id,
        NotifyOrgMetricSignOff::EVENT_APPROVED,
        (string) $approverUser->getKey()
    );

    $job->handle(app(LogOrgMetricEvent::class));

    Notification::assertNothingSent();
});
