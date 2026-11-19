<?php

use App\Actions\Audit\LogEngagementEvent;
use App\Enums\MembershipRole;
use App\Jobs\SendEngagementInvites;
use App\Models\Engagement;
use App\Models\Organization;
use App\Models\PendingMembership;
use App\Models\User;
use App\Notifications\PendingMembershipInvited;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

it('sends pending membership invitations and records audit logs', function () {
    Notification::fake();

    $organization = Organization::factory()->create();
    $actor = User::factory()->create();
    $engagement = Engagement::factory()->for($organization)->create();

    $pending = PendingMembership::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'email' => 'invitee@example.com',
        'role' => MembershipRole::ADMIN,
        'invited_by' => $actor->id,
    ]);

    $job = new SendEngagementInvites(
        (string) $organization->id,
        (string) $engagement->id,
        [$pending->id],
        (string) $actor->id
    );

    $job->handle(app(LogEngagementEvent::class));

    Notification::assertSentOnDemand(PendingMembershipInvited::class, function ($notification, $channels, $notifiable) use ($pending) {
        expect($channels)->toContain('mail');
        expect($notifiable)->toBeInstanceOf(AnonymousNotifiable::class);
        expect($notifiable->routes['mail'] ?? null)->toBe($pending->email);

        return $notification->pending->is($pending);
    });

    $this->assertDatabaseHas('audit_logs', [
        'organization_id' => $organization->id,
        'action' => 'engagement.invitation.dispatched',
        'subject_id' => $pending->id,
        'subject_type' => PendingMembership::class,
    ]);
});
