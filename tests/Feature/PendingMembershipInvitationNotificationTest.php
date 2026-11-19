<?php

use App\Enums\MembershipRole;
use App\Models\Organization;
use App\Models\PendingMembership;
use App\Models\User;
use App\Notifications\PendingMembershipInvited;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

it('dispatches the pending membership invited notification when a pending membership is created', function () {
    Notification::fake();

    $pending = PendingMembership::factory()->create([
        'email' => 'invitee@example.com',
    ]);

    Notification::assertSentOnDemand(PendingMembershipInvited::class, function ($notification, $channels, $notifiable) use ($pending) {
        expect($channels)->toContain('mail');
        expect($notifiable)->toBeInstanceOf(AnonymousNotifiable::class);
        expect($notifiable->routes['mail'] ?? null)->toBe('invitee@example.com');
        expect($notification->pending->is($pending))->toBeTrue();

        return true;
    });
});

it('includes the signed acceptance url in the pending membership invitation mail', function () {
    Notification::fake();

    Config::set('invitations.pending.accept_ttl_minutes', 90);
    Carbon::setTestNow($now = Carbon::now());

    $pending = PendingMembership::factory()
        ->for(Organization::factory()->state(['name' => 'Acme Org']), 'organization')
        ->for(User::factory()->state(['name' => 'Alex Inviter']), 'inviter')
        ->create([
            'email' => 'invitee@example.com',
            'role' => MembershipRole::ADMIN,
        ]);

    Notification::assertSentOnDemand(PendingMembershipInvited::class, function ($notification, $channels, $notifiable) use ($pending, $now) {
        $mailMessage = $notification->toMail($notifiable);

        $expectedExpiry = $now->copy()->addMinutes(90);

        expect($mailMessage->actionUrl)->toBe(URL::temporarySignedRoute('invites.accept', $expectedExpiry, [
            'pendingMembership' => $pending,
        ]));
        $rendered = (string) $mailMessage->render();

        expect($rendered)->toContain('Acme Org');
        expect($rendered)->toContain('Alex Inviter');
        expect($rendered)->toContain('Admin</strong> role');
        $expectedRegisterUrl = route('register', [
            'pending_membership_id' => $pending->id,
            'email' => 'invitee@example.com',
        ]);

        expect($rendered)->toContain('To accept, sign in with this email or create an account first, then confirm access.');
        $encodedRegisterUrl = e($expectedRegisterUrl);

        expect($rendered)->toContain('Need an account? Register now at');
        expect($rendered)->toContain('<a href="'.$encodedRegisterUrl.'"');
        expect($rendered)->toContain('>'.$encodedRegisterUrl.'</a>');
        expect($rendered)->toContain('This secure link expires on '.$expectedExpiry->toDayDateTimeString().'.');

        return true;
    });

    Carbon::setTestNow();
});
