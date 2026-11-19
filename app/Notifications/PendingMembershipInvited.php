<?php

namespace App\Notifications;

use App\Enums\MembershipRole;
use App\Models\PendingMembership;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;

class PendingMembershipInvited extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public PendingMembership $pending)
    {
        $this->afterCommit();

        $this->pending->loadMissing(['organization', 'inviter']);
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $expiresAt = Carbon::now()->addMinutes(
            (int) Config::get('invitations.pending.accept_ttl_minutes', 60 * 24 * 7)
        );

        $acceptUrl = URL::temporarySignedRoute(
            'invites.accept',
            $expiresAt,
            [
                'pendingMembership' => $this->pending,
            ]
        );

        $organizationName = $this->pending->organization?->name;
        $inviterName = $this->pending->inviter?->name;
        $role = $this->pending->role instanceof MembershipRole
            ? $this->pending->role
            : MembershipRole::tryFrom((string) $this->pending->role);

        $registerUrl = URL::route('register', [
            'pending_membership_id' => $this->pending->getKey(),
            'email' => $this->pending->email,
        ]);

        return (new MailMessage)
            ->subject('You\'ve been invited to join '.($organizationName ?: 'an organization'))
            ->action('Accept invitation', $acceptUrl)
            ->markdown('mail.pending_membership_invited', [
                'organizationName' => $organizationName,
                'inviterName' => $inviterName,
                'roleLabel' => $role ? $this->roleLabel($role) : null,
                'acceptUrl' => $acceptUrl,
                'expiresAt' => $expiresAt,
                'appName' => Config::get('app.name'),
                'registerUrl' => $registerUrl,
            ]);
    }

    private function roleLabel(MembershipRole $role): string
    {
        return match ($role) {
            MembershipRole::OWNER => 'Owner',
            MembershipRole::ADMIN => 'Admin',
            MembershipRole::MEMBER => 'Member',
            MembershipRole::VIEWER => 'Viewer',
        };
    }
}
