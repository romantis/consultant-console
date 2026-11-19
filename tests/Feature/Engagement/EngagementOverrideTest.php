<?php

use App\Enums\EngagementStatus;
use App\Models\AuditLog;
use App\Models\Engagement;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('allows overriding blocked workshop transition when a reason is provided and logs audit scope', function () {
    [$organization, $engagement, $user] = engagementOverrideContext();

    $overrideReason = 'Workshop completed offline with consultant confirmation.';

    $this->actingAs($user)
        ->patch(route('org.engagements.status.update', [$organization, $engagement]), [
            'status' => EngagementStatus::IN_WORKSHOP->value,
            'override' => true,
            'override_reason' => $overrideReason,
        ])
        ->assertRedirect();

    $audit = AuditLog::query()
        ->where('action', 'engagement.status.override_applied')
        ->latest('created_at')
        ->first();

    expect($engagement->refresh()->status)->toBe(EngagementStatus::IN_WORKSHOP)
        ->and($audit)->not->toBeNull()
        ->and($audit?->organization_id)->toBe($organization->id)
        ->and($audit?->user_id)->toBe($user->id)
        ->and($audit?->subject_type)->toBe(Engagement::class)
        ->and($audit?->subject_id)->toBe($engagement->id)
        ->and($audit?->properties['from_status'] ?? null)->toBe(EngagementStatus::IN_REVIEW->value)
        ->and($audit?->properties['to_status'] ?? null)->toBe(EngagementStatus::IN_WORKSHOP->value)
        ->and($audit?->properties['blocked_reason'] ?? null)->toBe('missing_valid_session')
        ->and($audit?->properties['override_reason'] ?? null)->toBe($overrideReason)
        ->and($audit?->properties['scope']['organization_id'] ?? null)->toBe($organization->id)
        ->and($audit?->properties['scope']['engagement_id'] ?? null)->toBe($engagement->id);
});

it('rejects blocked transitions when override is not requested', function () {
    [$organization, $engagement, $user] = engagementOverrideContext();

    $this->actingAs($user)
        ->patchJson(route('org.engagements.status.update', [$organization, $engagement]), [
            'status' => EngagementStatus::IN_WORKSHOP->value,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['status']);

    expect($engagement->refresh()->status)->toBe(EngagementStatus::IN_REVIEW);

    $overrideAuditExists = AuditLog::query()
        ->where('action', 'engagement.status.override_applied')
        ->exists();

    expect($overrideAuditExists)->toBeFalse();
});

it('rejects override attempts when reason is missing', function () {
    [$organization, $engagement, $user] = engagementOverrideContext();

    $this->actingAs($user)
        ->patchJson(route('org.engagements.status.update', [$organization, $engagement]), [
            'status' => EngagementStatus::IN_WORKSHOP->value,
            'override' => true,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['override_reason']);

    expect($engagement->refresh()->status)->toBe(EngagementStatus::IN_REVIEW);

    $overrideAuditExists = AuditLog::query()
        ->where('action', 'engagement.status.override_applied')
        ->exists();

    expect($overrideAuditExists)->toBeFalse();
});

/**
 * @return array{Organization, Engagement, User}
 */
function engagementOverrideContext(): array
{
    $organization = Organization::factory()->create();
    $internal = makeInternalOrg();
    $user = User::factory()->create();
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

    return [$organization, $engagement, $user];
}
