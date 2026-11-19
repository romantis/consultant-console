<?php

use App\Enums\EngagementStatus;
use App\Jobs\ProcessAssignmentReminders;
use App\Models\Engagement;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('queues reminder processing when engagement is awaiting forms', function () {
    Queue::fake();

    $user = User::factory()->create();
    $internal = makeInternalOrg();
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
        'status' => EngagementStatus::AWAITING_FORMS->value,
    ]);

    Membership::factory()->admin()->create([
        'organization_id' => $organization->id,
        'user_id' => $user->id,
    ]);
    Membership::factory()->admin()->create([
        'organization_id' => $internal->id,
        'user_id' => $user->id,
    ]);

    $response = $this->actingAs($user)
        ->from('/reminder-pass')
        ->post(route('org.engagements.reminders.store', [$organization, $engagement]));

    $response->assertRedirect('/reminder-pass')
        ->assertSessionHas('success', 'Reminder jobs queued; assignments will be evaluated shortly.');

    Queue::assertPushed(ProcessAssignmentReminders::class, 1);
});

it('does not queue reminder processing outside awaiting forms', function () {
    Queue::fake();

    $user = User::factory()->create();
    $internal = makeInternalOrg();
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
        'status' => EngagementStatus::IN_REVIEW->value,
    ]);

    Membership::factory()->admin()->create([
        'organization_id' => $organization->id,
        'user_id' => $user->id,
    ]);
    Membership::factory()->admin()->create([
        'organization_id' => $internal->id,
        'user_id' => $user->id,
    ]);

    $response = $this->actingAs($user)
        ->from('/reminder-pass')
        ->post(route('org.engagements.reminders.store', [$organization, $engagement]));

    $response->assertRedirect('/reminder-pass')
        ->assertSessionHas('error', 'Reminder pass is available only when engagement status is Awaiting Forms.');

    Queue::assertNotPushed(ProcessAssignmentReminders::class);
});
