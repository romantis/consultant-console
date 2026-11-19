<?php

use App\Models\Metric;
use App\Models\MetricFacilitationBrief;
use App\Models\User;
use Database\Seeders\MetricTemplateSeeder;
use Database\Seeders\StorySeeds\FocusTimeAlignmentStorySeeder;

it('clears stale prepared facilitation plans only for the reseeded Focus Time metric', function () {
    $this->seed(MetricTemplateSeeder::class);
    $this->seed(FocusTimeAlignmentStorySeeder::class);

    $storyMetric = Metric::query()
        ->with(['engagement', 'organization'])
        ->where('key', 'focus-time-alignment')
        ->firstOrFail();
    $unrelatedMetric = Metric::factory()->create();

    $storyBrief = MetricFacilitationBrief::query()->create([
        'metric_id' => $storyMetric->id,
        'schema_version' => '2.0',
        'status' => 'ready_for_workshop',
        'source_fingerprint' => 'stale-focus-time-fingerprint',
        'document' => staleFacilitationDocument('Lock: Focus Hours Target'),
        'revision' => 1,
    ]);
    $unrelatedBrief = MetricFacilitationBrief::query()->create([
        'metric_id' => $unrelatedMetric->id,
        'schema_version' => '2.0',
        'status' => 'ready_for_workshop',
        'source_fingerprint' => 'unrelated-fingerprint',
        'document' => staleFacilitationDocument('Keep unrelated consultant edit'),
        'revision' => 1,
    ]);

    $this->seed(FocusTimeAlignmentStorySeeder::class);

    expect(MetricFacilitationBrief::query()->whereKey($storyBrief->id)->exists())->toBeFalse()
        ->and(MetricFacilitationBrief::query()->whereKey($unrelatedBrief->id)->exists())->toBeTrue();

    $staff = User::query()->where('email', 'admin@e.test')->firstOrFail();
    $response = $this->actingAs($staff)
        ->postJson(route('org.engagements.metrics.prep.facilitation.store', [
            'organization' => $storyMetric->organization,
            'engagement' => $storyMetric->engagement,
            'metric' => $storyMetric,
        ]))
        ->assertCreated();

    $titles = collect($response->json('data.document.run_of_show'))
        ->pluck('title')
        ->all();

    expect($titles)
        ->toContain('Set the daily focus-hours target the team will plan around.')
        ->toContain('Agree which meeting rules protect focus blocks.')
        ->toContain('Agree how notifications are handled during focus time.')
        ->not->toContain('Lock: Focus Hours Target');
});

/**
 * @return array<string, mixed>
 */
function staleFacilitationDocument(string $segmentTitle): array
{
    return [
        'schema_version' => '2.0',
        'status' => 'ready_for_workshop',
        'metric_id' => 'stale-metric',
        'metric_name' => 'Stale metric',
        'workshop_thesis' => [
            'text' => 'Stale thesis.',
            'citation_ids' => [],
        ],
        'decisions_to_lock' => [[
            'id' => 'decision:stale',
            'field_keys' => ['stale_field'],
            'priority' => 'high',
            'title' => 'Stale field',
            'agenda_topic_ids' => ['field:stale'],
            'decision_prompt' => [
                'text' => 'Stale decision.',
                'citation_ids' => [],
            ],
        ]],
        'run_of_show' => [[
            'id' => 'segment:stale',
            'title' => $segmentTitle,
            'duration_minutes' => 10,
            'decision_ids' => ['decision:stale'],
            'purpose' => [
                'text' => 'Stale saved plan.',
                'provenance' => 'consultant_authored',
            ],
        ]],
        'expected_outputs' => [],
        'readiness_gaps' => [],
    ];
}
