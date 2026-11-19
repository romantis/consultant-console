<?php

use App\Actions\Metrics\ApproveSignOff;
use App\Enums\MetricStatus;
use App\Models\AuditLog;
use App\Models\FinalDefinition;
use App\Models\Metric;
use App\Models\MetricParticipant;
use App\Models\MetricTemplate;
use App\Models\OrgMetricParticipant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function makeMetricWithFinalDefinition(?MetricStatus $status = null): array
{
    $metric = Metric::factory()->create([
        'status' => $status ?? MetricStatus::IN_REVIEW,
    ]);

    $ownerParticipant = MetricParticipant::factory()->approver()->create([
        'metric_id' => $metric->id,
    ]);

    OrgMetricParticipant::factory()->create([
        'org_metric_id' => $metric->org_metric_id,
        'user_id' => $ownerParticipant->user_id,
        'responsibility' => 'owner',
    ]);

    $finalDefinition = FinalDefinition::factory()->create([
        'metric_id' => $metric->id,
        'definition_json' => [
            'value' => ['label' => 'Metric Value', 'value' => 'Aligned'],
        ],
        'owner_participant_id' => $ownerParticipant->id,
    ]);

    return [$metric->fresh(), $finalDefinition];
}

it('approves sign-off and finalizes the metric', function () {
    [$metric, $finalDefinition] = makeMetricWithFinalDefinition();

    $actor = User::factory()->create();

    OrgMetricParticipant::factory()->create([
        'org_metric_id' => $metric->org_metric_id,
        'user_id' => $actor->id,
        'responsibility' => 'approver',
    ]);

    $result = app(ApproveSignOff::class)->handle($metric, [
        'decision' => 'approve',
        'comment' => 'Ship it',
    ], $actor);

    $metric->refresh();
    $finalDefinition->refresh();

    expect($metric->status)->toBe(MetricStatus::FINALIZED)
        ->and($finalDefinition->signed_off_by)->toBe($actor->id)
        ->and($finalDefinition->signed_off_at)->not->toBeNull()
        ->and($result['decision'])->toBe('approve')
        ->and($result['comment'])->toBe('Ship it');

    $approvedLog = AuditLog::query()
        ->where('action', 'metric.final-definition.signed-off')
        ->latest('created_at')
        ->first();

    expect($approvedLog)->not->toBeNull()
        ->and($approvedLog->metric_id)->toBe((string) $metric->id)
        ->and($approvedLog->properties['decision'] ?? null)->toBe('approve')
        ->and($approvedLog->properties['comment'] ?? null)->toBe('Ship it');
});

it('records change requests and reopens the metric for edits', function () {
    [$metric, $finalDefinition] = makeMetricWithFinalDefinition(MetricStatus::IN_WORKSHOP);

    $reviewer = User::factory()->create();

    $finalDefinition->forceFill([
        'status' => 'Approved',
        'signed_off_at' => now(),
        'signed_off_by' => $reviewer->id,
    ])->save();

    $actor = User::factory()->create();

    $result = app(ApproveSignOff::class)->handle($metric, [
        'decision' => 'request_changes',
        'comment' => 'Need updated owner assignments',
    ], $actor);

    $metric->refresh();
    $finalDefinition->refresh();

    expect($metric->status)->toBe(MetricStatus::IN_REVIEW)
        ->and($finalDefinition->signed_off_at)->toBeNull()
        ->and($finalDefinition->signed_off_by)->toBeNull()
        ->and($finalDefinition->status)->toBe('NeedsRevision')
        ->and($result['decision'])->toBe('request_changes');

    $changesLog = AuditLog::query()
        ->where('action', 'metric.sign-off.changes-requested')
        ->latest('created_at')
        ->first();

    expect($changesLog)->not->toBeNull()
        ->and($changesLog->metric_id)->toBe((string) $metric->id)
        ->and($changesLog->properties['decision'] ?? null)->toBe('request_changes')
        ->and($changesLog->properties['comment'] ?? null)->toBe('Need updated owner assignments');
});

it('rejects approving sign-off when required fields are missing', function () {
    $template = MetricTemplate::factory()->create([
        'form_schema_json' => [
            'fields' => [
                [
                    'name' => 'summary',
                    'type' => 'text',
                    'label' => 'Summary',
                    'required' => true,
                    'classification' => [
                        'role' => 'definition',
                    ],
                ],
                [
                    'name' => 'target',
                    'type' => 'text',
                    'label' => 'Target',
                    'required' => true,
                    'classification' => [
                        'role' => 'definition',
                    ],
                ],
            ],
        ],
    ]);

    $metric = Metric::factory()->create([
        'status' => MetricStatus::IN_REVIEW,
        'metric_template_id' => $template->id,
    ]);

    $ownerParticipant = MetricParticipant::factory()->approver()->create([
        'metric_id' => $metric->id,
    ]);

    OrgMetricParticipant::factory()->create([
        'org_metric_id' => $metric->org_metric_id,
        'user_id' => $ownerParticipant->user_id,
        'responsibility' => 'owner',
    ]);

    $finalDefinition = FinalDefinition::factory()->create([
        'metric_id' => $metric->id,
        'definition_json' => [
            'summary' => ['label' => 'Summary', 'value' => 'Aligned'],
            'target' => ['label' => 'Target', 'value' => null],
        ],
        'owner_participant_id' => $ownerParticipant->id,
    ]);

    try {
        app(ApproveSignOff::class)->handle($metric, [
            'decision' => 'approve',
            'comment' => null,
        ], $ownerParticipant->user);
        $this->fail('Expected validation exception.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('final_definition_id')
            ->and($exception->errors()['final_definition_id'][0] ?? null)
            ->toBe('Complete required fields (Target) before approving sign-off.');
    }
});
