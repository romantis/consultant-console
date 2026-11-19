<?php

use App\Actions\Engagements\LaunchEngagement;
use App\Enums\MembershipRole;
use App\Models\Membership;
use App\Models\MetricTemplate;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('assigns pending membership roles based on stakeholder roles when launching an engagement', function () {
    $organization = Organization::factory()->create();
    $ownerMembership = Membership::factory()
        ->for($organization)
        ->owner()
        ->create();

    /** @var User $actor */
    $actor = $ownerMembership->user;

    $templates = MetricTemplate::factory()->count(3)->create();

    $buildMetric = function (MetricTemplate $template, string $key) use ($ownerMembership) {
        return [
            'template_id' => (string) $template->id,
            'canonical_metric_id' => (string) $template->canonical_metric_id,
            'key' => $key,
            'name' => ucfirst($key),
            'description' => 'Example metric',
            'participants' => [
                ['participant_id' => 'invite:admin@example.com', 'role' => 'Contributor'],
                ['participant_id' => (string) $ownerMembership->id, 'role' => 'Contributor'],
                ['participant_id' => (string) $ownerMembership->id, 'role' => 'Approver'],
                ['participant_id' => 'invite:viewer@example.com', 'role' => 'Observer'],
            ],
        ];
    };

    $payload = [
        'organization_id' => (string) $organization->id,
        'name' => 'Stakeholder Role Mapping',
        'timeline_start' => now()->toDateString(),
        'timeline_end' => now()->addWeek()->toDateString(),
        'stakeholders' => [
            [
                'participant_id' => 'invite:admin@example.com',
                'email' => 'admin@example.com',
                'name' => 'Admin Stakeholder',
                'role' => 'Admin',
            ],
            [
                'participant_id' => 'invite:viewer@example.com',
                'email' => 'viewer@example.com',
                'name' => 'Viewer Stakeholder',
                'role' => 'Viewer',
            ],
        ],
        'invited_emails' => ['admin@example.com', 'viewer@example.com'],
        'metrics' => [
            $buildMetric($templates[0], 'metric-one'),
            $buildMetric($templates[1], 'metric-two'),
            $buildMetric($templates[2], 'metric-three'),
        ],
    ];

    app(LaunchEngagement::class)->handle($payload, $actor);

    $pending = $organization->pendingMemberships()->get()->keyBy('email');

    expect($pending['admin@example.com']->role)->toBe(MembershipRole::ADMIN)
        ->and($pending['viewer@example.com']->role)->toBe(MembershipRole::VIEWER);
});
