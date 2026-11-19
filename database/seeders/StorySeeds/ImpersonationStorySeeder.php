<?php

namespace Database\Seeders\StorySeeds;

use App\Enums\MembershipRole;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;

class ImpersonationStorySeeder extends Seeder
{
    public function run(): void
    {
        $consultant = User::query()->where('email', 'consultant@example.test')->first();
        $client = Organization::query()->where('slug', 'acme')->first();

        if (! $consultant || ! $client) {
            return;
        }

        $internal = Organization::query()->where('is_internal', true)->first();

        $entries = [
            [
                'result' => AuditLog::RESULT_STARTED,
                'action' => AuditLog::ACTION_IMPERSONATION_STARTED,
                'role' => MembershipRole::OWNER,
                'subject_id' => (string) str()->ulid(),
                'summary' => 'Console Owner started impersonating Acme as an Owner.',
                'justification' => 'QA seed: verify owner-level controls.',
                'target_organization_id' => $client->id,
            ],
            [
                'result' => AuditLog::RESULT_ROLE_CHANGED,
                'action' => AuditLog::ACTION_IMPERSONATION_ROLE_CHANGED,
                'role' => MembershipRole::VIEWER,
                'subject_id' => (string) str()->ulid(),
                'summary' => 'Console Owner downgraded to Viewer during the same session.',
                'justification' => 'QA seed: confirm read-only policy coverage.',
                'target_organization_id' => $client->id,
            ],
            [
                'result' => AuditLog::RESULT_STOPPED,
                'action' => AuditLog::ACTION_IMPERSONATION_STOPPED,
                'role' => MembershipRole::VIEWER,
                'subject_id' => (string) str()->ulid(),
                'summary' => 'Console Owner stopped impersonating Acme and returned to workspace.',
                'justification' => 'QA seed: ensure stop flow clears cookies.',
                'target_organization_id' => $client->id,
            ],
            [
                'result' => AuditLog::RESULT_DENIED,
                'action' => AuditLog::ACTION_IMPERSONATION_DENIED,
                'role' => MembershipRole::ADMIN,
                'subject_id' => (string) str()->ulid(),
                'summary' => 'Denied attempt to impersonate the internal Metric Harmony workspace.',
                'justification' => 'QA seed: lock out internal organization.',
                'target_organization_id' => $internal?->id ?? $client->id,
            ],
        ];

        foreach ($entries as $entry) {
            $properties = [
                'summary' => $entry['summary'],
                'impersonated_role' => $entry['role']->value,
                'justification' => $entry['justification'],
                'result' => $entry['result'],
                'impersonated_organization_id' => $entry['target_organization_id'],
                'source' => 'seeded-example',
            ];

            AuditLog::query()->updateOrCreate(
                [
                    'subject_type' => 'impersonation-seed',
                    'subject_id' => $entry['subject_id'],
                    'action' => $entry['action'],
                ],
                [
                    'organization_id' => $entry['target_organization_id'],
                    'user_id' => $consultant->id,
                    'properties' => $properties,
                ]
            );
        }
    }
}
