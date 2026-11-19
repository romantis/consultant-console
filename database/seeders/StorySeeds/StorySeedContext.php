<?php

namespace Database\Seeders\StorySeeds;

use App\Enums\MembershipRole;
use App\Models\Engagement;
use App\Models\Membership;
use App\Models\MetricTemplate;
use App\Models\Organization;
use App\Models\PendingMembership;
use App\Models\User;
use Illuminate\Support\Collection;

class StorySeedContext
{
    public function internalOrganization(): Organization
    {
        return Organization::firstOrCreate(
            ['slug' => 'metric-harmony'],
            [
                'name' => 'Metric Harmony',
                'is_internal' => true,
                'settings_json' => [
                    'locale' => 'en',
                    'currency' => 'USD',
                    'timezone' => 'UTC',
                ],
            ]
        );
    }

    public function staffAdmin(): User
    {
        $user = User::firstOrCreate(
            ['email' => 'admin@e.test'],
            ['name' => 'Console Admin', 'password' => 'password']
        );

        Membership::firstOrCreate(
            ['organization_id' => $this->internalOrganization()->id, 'user_id' => $user->id],
            ['role' => MembershipRole::ADMIN->value, 'affiliation' => 'Consultant HQ']
        );

        return $user;
    }

    public function ensureConsultantAccess(
        Organization $organization,
        ?User $user = null,
        MembershipRole $role = MembershipRole::OWNER
    ): Membership {
        $user ??= $this->staffAdmin();

        return Membership::firstOrCreate(
            ['organization_id' => $organization->id, 'user_id' => $user->id],
            ['role' => $role->value, 'affiliation' => 'Consultant']
        );
    }

    public function ensureClientOrganization(string $slug, string $name, array $settings = []): Organization
    {
        return Organization::firstOrCreate(
            ['slug' => $slug],
            [
                'name' => $name,
                'settings_json' => array_merge(
                    [
                        'locale' => 'en',
                        'currency' => 'USD',
                        'timezone' => 'UTC',
                    ],
                    $settings
                ),
            ]
        );
    }

    public function ensureMember(
        Organization $organization,
        string $email,
        string $name,
        MembershipRole $role,
        array $attributes = []
    ): Membership {
        $user = User::firstOrCreate(
            ['email' => strtolower($email)],
            ['name' => $name, 'password' => 'password']
        );

        return Membership::firstOrCreate(
            ['organization_id' => $organization->id, 'user_id' => $user->id],
            array_merge(['role' => $role->value], $attributes)
        );
    }

    public function ensurePendingInvite(
        Organization $organization,
        Engagement $engagement,
        string $email,
        string $name,
        MembershipRole $role,
        User $inviter,
        array $attributes = []
    ): PendingMembership {
        return PendingMembership::firstOrCreate(
            [
                'organization_id' => $organization->id,
                'engagement_id' => $engagement->id,
                'email' => strtolower($email),
            ],
            array_merge([
                'name' => $name,
                'role' => $role,
                'invited_by' => $inviter->id,
                'affiliation' => $attributes['affiliation'] ?? null,
            ], $attributes)
        );
    }

    public function metricTemplates(int $count = 5): Collection
    {
        $templates = MetricTemplate::query()->take($count)->get();

        if ($templates->count() < $count) {
            MetricTemplate::factory()->count($count - $templates->count())->create();
            $templates = MetricTemplate::query()->take($count)->get();
        }

        $demo = $this->classificationDemoTemplate();

        return collect([$demo])
            ->merge($templates)
            ->unique('id')
            ->take($count)
            ->values();
    }

    protected function classificationDemoTemplate(): MetricTemplate
    {
        return MetricTemplate::updateOrCreate(
            ['key' => 'story-classification-demo'],
            [
                'name' => 'Field Role Story Demo',
                'description' => 'Ensures story seeds include definition, context, governance, and diagnostic fields.',
                'form_schema_json' => [
                    'fields' => [
                        [
                            'name' => 'definition_anchor',
                            'type' => 'number',
                            'label' => 'Definition anchor value',
                            'required' => true,
                            'classification' => [
                                'role' => 'definition',
                            ],
                        ],
                        [
                            'name' => 'supporting_context',
                            'type' => 'textarea',
                            'label' => 'Supporting context for reviewers',
                            'required' => false,
                            'meta' => true,
                            'classification' => [
                                'role' => 'context',
                            ],
                        ],
                        [
                            'name' => 'governance_contact',
                            'type' => 'text',
                            'label' => 'Governance contact',
                            'required' => false,
                            'meta' => true,
                            'classification' => [
                                'role' => 'governance',
                            ],
                        ],
                        [
                            'name' => 'diagnostic_trace',
                            'type' => 'textarea',
                            'label' => 'Diagnostic trace notes',
                            'required' => false,
                            'meta' => true,
                            'classification' => [
                                'role' => 'diagnostic',
                            ],
                        ],
                    ],
                ],
            ]
        );
    }
}
