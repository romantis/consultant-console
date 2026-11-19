<?php

use App\Actions\Audit\LogChangeProposalEvent;
use App\Actions\Metrics\MergeChangeProposal;
use App\Actions\Metrics\OpenChangeProposal;
use App\Enums\ChangeProposalStatus;
use App\Enums\EngagementStatus;
use App\Enums\MetricStatus;
use App\Http\Controllers\ChangeProposalController;
use App\Jobs\NotifyChangeProposalApprovers;
use App\Models\AuditLog;
use App\Models\Engagement;
use App\Models\FinalDefinition;
use App\Models\Membership;
use App\Models\Metric;
use App\Models\MetricParticipant;
use App\Models\Organization;
use App\Models\OrgFinalDefinition;
use App\Models\OrgMetricParticipant;
use App\Models\User;
use App\Notifications\ChangeProposalReviewRequested;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('metric-harmony.features.change_proposals', true);
});

it('governs change proposals from open through merge for finalized metrics', function () {
    if (
        ! Schema::hasTable('change_proposals')
        || ! Schema::hasColumn('final_definitions', 'version')
        || ! class_exists(OpenChangeProposal::class)
        || ! class_exists(MergeChangeProposal::class)
        || ! class_exists(ChangeProposalController::class)
    ) {
        $this->markTestSkipped('Change proposal APIs are not available yet. Complete T051–T052 before enabling this spec.');
    }

    Carbon::setTestNow(Carbon::parse('2025-03-05 10:15:00'));

    Queue::fake();
    Notification::fake();

    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
        'status' => EngagementStatus::FINALIZED,
    ]);

    $consultant = User::factory()->create();
    Membership::factory()->owner()->create([
        'organization_id' => $organization->id,
        'user_id' => $consultant->id,
    ]);

    $approverUser = User::factory()->create();
    Membership::factory()->member()->create([
        'organization_id' => $organization->id,
        'user_id' => $approverUser->id,
    ]);

    /** @var Metric $metric */
    $metric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'status' => MetricStatus::FINALIZED,
        'name' => 'Net Revenue Retention',
    ]);

    $approverParticipant = MetricParticipant::factory()->approver()->create([
        'metric_id' => $metric->id,
        'user_id' => $approverUser->id,
    ]);

    $orgOwnerParticipant = OrgMetricParticipant::factory()->create([
        'org_metric_id' => $metric->org_metric_id,
        'user_id' => $approverUser->id,
        'responsibility' => 'owner',
    ]);

    /** @var FinalDefinition $finalDefinition */
    $finalDefinition = FinalDefinition::factory()->create([
        'metric_id' => $metric->id,
        'user_id' => $consultant->id,
        'status' => 'Finalized',
        'definition_json' => [
            'summary' => ['label' => 'Summary', 'value' => 'Baseline throughput'],
            'owner' => ['label' => 'Owner', 'value' => 'RevOps'],
        ],
        'notes' => 'Initial final definition agreed in Q1',
        'signed_off_at' => now()->subDays(7),
        'signed_off_by' => $approverUser->id,
        'owner_participant_id' => $approverParticipant->id,
    ]);

    $initialVersion = 1;
    $finalDefinition->forceFill(['version' => $initialVersion])->save();

    OrgFinalDefinition::factory()->create([
        'org_metric_id' => $metric->org_metric_id,
        'source_metric_id' => $metric->id,
        'source_metric_template_id' => $metric->metric_template_id,
        'status' => 'Approved',
        'version' => $initialVersion,
        'definition_json' => $finalDefinition->definition_json,
        'notes' => $finalDefinition->notes,
        'owner_participant_id' => $orgOwnerParticipant->id,
        'signed_off_at' => $finalDefinition->signed_off_at,
        'signed_off_by' => $finalDefinition->signed_off_by,
    ]);

    $proposalRequest = [
        'rationale' => 'Need to include post-acquisition adjustments for FY26 baseline.',
        'payload' => [
            'summary' => ['label' => 'Summary', 'value' => 'Adjusted for FY26 targets'],
            'owner' => ['label' => 'Owner', 'value' => 'Product Ops'],
            'notes' => 'Incorporates retention uplift assumptions.',
        ],
        'approver_ids' => [(string) $approverParticipant->id],
    ];

    $response = $this->actingAs($consultant)
        ->postJson("/api/metrics/{$metric->getKey()}/change-proposals", $proposalRequest);

    $response->assertStatus(201)->assertJson([
        'metric_id' => (string) $metric->getKey(),
        'status' => ChangeProposalStatus::OPEN->value,
        'rationale' => $proposalRequest['rationale'],
    ]);

    $proposalId = $response->json('id');
    expect($proposalId)->not->toBeNull();

    Queue::assertPushed(NotifyChangeProposalApprovers::class, function ($job) use ($proposalId, $consultant, $metric) {
        return $job->proposalId === $proposalId
            && $job->actorId === (string) $consultant->id
            && $job->organizationId === (string) $metric->organization_id
            && $job->engagementId === (string) $metric->engagement_id
            && $job->metricId === (string) $metric->getKey();
    });

    $job = new NotifyChangeProposalApprovers(
        $proposalId,
        (string) $metric->organization_id,
        (string) $metric->engagement_id,
        (string) $metric->getKey(),
        (string) $consultant->id
    );
    $job->handle(app(LogChangeProposalEvent::class));

    Notification::assertSentTo(
        $approverUser,
        ChangeProposalReviewRequested::class,
        fn ($notification) => $notification->proposal->getKey() === $proposalId
    );

    $notificationLog = AuditLog::query()
        ->where('action', 'metric.change-proposal.notified')
        ->latest()
        ->first();

    expect($notificationLog)->not->toBeNull()
        ->and($notificationLog->properties['approver_user_ids'] ?? [])->toContain((string) $approverUser->id);

    $proposalRecord = DB::table('change_proposals')
        ->where('id', $proposalId)
        ->first();

    expect($proposalRecord)->not->toBeNull()
        ->and($proposalRecord->metric_id)->toBe((string) $metric->getKey())
        ->and($proposalRecord->status)->toBe(ChangeProposalStatus::OPEN->value);

    $storedApproverIds = json_decode($proposalRecord->approver_ids ?? '[]', true);
    expect($storedApproverIds)->toContain((string) $approverParticipant->id);

    $storedPayload = json_decode($proposalRecord->payload ?? '{}', true);
    expect(data_get($storedPayload, 'summary.value'))->toBe('Adjusted for FY26 targets');

    $openLog = AuditLog::query()
        ->where('action', 'metric.change-proposal.opened')
        ->latest()
        ->first();

    expect($openLog)->not->toBeNull()
        ->and($openLog->metric_id)->toBe((string) $metric->getKey())
        ->and($openLog->properties['proposal_id'] ?? null)->toBe($proposalId);

    $decisionResponse = $this->actingAs($approverUser)
        ->postJson("/api/change-proposals/{$proposalId}/decision", [
            'decision' => 'approve',
            'comment' => 'Looks good once handbook updated.',
        ]);

    $decisionResponse->assertOk()->assertJson([
        'status' => ChangeProposalStatus::APPROVED->value,
        'decision' => 'approve',
    ]);

    $proposalRecord = DB::table('change_proposals')
        ->where('id', $proposalId)
        ->first();

    expect($proposalRecord)->not->toBeNull()
        ->and($proposalRecord->status)->toBe(ChangeProposalStatus::APPROVED->value)
        ->and($proposalRecord->approved_at)->not->toBeNull();

    $decisionLog = AuditLog::query()
        ->where('action', 'metric.change-proposal.decision')
        ->latest()
        ->first();

    expect($decisionLog)->not->toBeNull()
        ->and($decisionLog->properties['decision'] ?? null)->toBe('approve')
        ->and($decisionLog->properties['comment'] ?? null)->toBe('Looks good once handbook updated.');

    $mergeResponse = $this->actingAs($consultant)
        ->postJson("/api/change-proposals/{$proposalId}/merge");

    $mergeResponse->assertOk()->assertJson([
        'status' => ChangeProposalStatus::MERGED->value,
        'final_definition_version' => $initialVersion + 1,
    ]);

    $proposalRecord = DB::table('change_proposals')
        ->where('id', $proposalId)
        ->first();

    expect($proposalRecord)->not->toBeNull()
        ->and($proposalRecord->status)->toBe(ChangeProposalStatus::MERGED->value)
        ->and($proposalRecord->merged_at)->not->toBeNull();

    $latestDefinition = FinalDefinition::query()
        ->where('metric_id', $metric->getKey())
        ->orderByDesc('version')
        ->first();

    expect($latestDefinition)->not->toBeNull()
        ->and($latestDefinition->version)->toBe($initialVersion + 1)
        ->and(data_get($latestDefinition->definition_json, 'summary.value'))->toBe('Adjusted for FY26 targets')
        ->and(data_get($latestDefinition->definition_json, 'owner.value'))->toBe('Product Ops');

    $latestOfficialDefinition = OrgFinalDefinition::query()
        ->where('org_metric_id', $metric->org_metric_id)
        ->orderByDesc('version')
        ->first();

    expect($latestOfficialDefinition)->not->toBeNull()
        ->and($latestOfficialDefinition?->version)->toBe($initialVersion + 1)
        ->and(data_get($latestOfficialDefinition?->definition_json, 'summary.value'))->toBe('Adjusted for FY26 targets');

    $mergedLog = AuditLog::query()
        ->where('action', 'metric.change-proposal.merged')
        ->latest()
        ->first();

    expect($mergedLog)->not->toBeNull()
        ->and($mergedLog->properties['proposal_id'] ?? null)->toBe($proposalId)
        ->and($mergedLog->properties['final_definition_version'] ?? null)->toBe($initialVersion + 1);

    Carbon::setTestNow();
});
