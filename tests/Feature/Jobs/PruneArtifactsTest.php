<?php

use App\Jobs\PruneArtifacts;
use App\Models\Engagement;
use App\Models\Form;
use App\Models\FormAssignment;
use App\Models\HandbookExport;
use App\Models\Metric;
use App\Models\NotificationAttempt;
use App\Models\Organization;
use App\Models\ReminderSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\artisan;

uses(RefreshDatabase::class);

it('prunes notification attempts older than the configured retention window', function () {
    config()->set('metric-harmony.retention.notification_attempts_days', 30);

    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
    ]);
    $metric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
    ]);
    $form = Form::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'metric_id' => $metric->id,
    ]);
    $assignee = User::factory()->create();

    $assignment = FormAssignment::factory()
        ->for($form, 'form')
        ->for($assignee, 'user')
        ->create([
            'metric_id' => $metric->id,
        ]);

    $reminder = ReminderSchedule::query()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'metric_id' => $metric->id,
        'form_assignment_id' => $assignment->id,
        'window' => ReminderSchedule::WINDOW_T_MINUS_24H,
        'channel' => ReminderSchedule::CHANNEL_EMAIL,
        'deliver_at' => now()->subDays(40),
        'status' => ReminderSchedule::STATUS_SENT,
    ]);

    $staleAttempt = NotificationAttempt::query()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'metric_id' => $metric->id,
        'form_assignment_id' => $assignment->id,
        'notification_id' => $reminder->id,
        'channel' => ReminderSchedule::CHANNEL_EMAIL,
        'provider_response' => ['message' => 'accepted'],
        'status' => NotificationAttempt::STATUS_SENT,
        'attempted_at' => now()->subDays(45),
    ]);

    $recentAttempt = NotificationAttempt::query()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'metric_id' => $metric->id,
        'form_assignment_id' => $assignment->id,
        'notification_id' => $reminder->id,
        'channel' => ReminderSchedule::CHANNEL_EMAIL,
        'provider_response' => ['message' => 'accepted'],
        'status' => NotificationAttempt::STATUS_SENT,
        'attempted_at' => now()->subDays(5),
    ]);

    Bus::dispatchSync(new PruneArtifacts(['notifications'], 30, null));

    expect(NotificationAttempt::query()->find($staleAttempt->id))->toBeNull();
    expect(NotificationAttempt::query()->find($recentAttempt->id))->not()->toBeNull();
});

it('removes handbook export records and files once the retention window passes', function () {
    config()->set('metric-harmony.retention.handbook_exports_days', 60);
    Storage::fake('handbooks');

    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
    ]);

    $stale = HandbookExport::query()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'format' => 'pdf',
        'storage_disk' => 'handbooks',
        'storage_path' => 'exports/stale.pdf',
        'status' => HandbookExport::STATUS_READY,
        'completed_at' => now()->subDays(120),
    ]);

    $recent = HandbookExport::query()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'format' => 'pdf',
        'storage_disk' => 'handbooks',
        'storage_path' => 'exports/recent.pdf',
        'status' => HandbookExport::STATUS_READY,
        'completed_at' => now()->subDays(10),
    ]);

    $published = HandbookExport::query()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'format' => 'pdf',
        'storage_disk' => 'handbooks',
        'storage_path' => 'exports/published.pdf',
        'status' => HandbookExport::STATUS_READY,
        'completed_at' => now()->subDays(120),
        'published_at' => now(),
    ]);

    Storage::disk('handbooks')->put('exports/stale.pdf', 'PDF');
    Storage::disk('handbooks')->put('exports/recent.pdf', 'PDF');
    Storage::disk('handbooks')->put('exports/published.pdf', 'PDF');

    Bus::dispatchSync(new PruneArtifacts(['handbook'], null, 60));

    Storage::disk('handbooks')->assertMissing('exports/stale.pdf');
    Storage::disk('handbooks')->assertExists('exports/recent.pdf');
    Storage::disk('handbooks')->assertExists('exports/published.pdf');

    expect(HandbookExport::query()->find($stale->id))->toBeNull();
    expect(HandbookExport::query()->find($recent->id))->not()->toBeNull();
    expect(HandbookExport::query()->find($published->id))->not()->toBeNull();
});

it('dispatches the prune artifacts job through the console command when queued', function () {
    Bus::fake();

    artisan('ops:prune-artifacts', [
        '--only' => 'handbook',
        '--queue' => true,
        '--handbook-retention' => 15,
    ])->assertExitCode(0);

    Bus::assertDispatched(PruneArtifacts::class, function (PruneArtifacts $job) {
        return $job->artifacts() === ['handbook'];
    });
});
