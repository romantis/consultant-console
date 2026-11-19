<?php

namespace Database\Factories;

use App\Enums\MembershipRole;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'user_id' => User::factory(),
            'action' => 'metric.definition.recorded',
            'subject_type' => 'metric-definition',
            'subject_id' => (string) str()->ulid(),
            'properties' => [
                'summary' => $this->faker->sentence(),
            ],
        ];
    }

    public function impersonation(?MembershipRole $role = null, ?string $result = null): self
    {
        return $this->state(function (array $attributes) use ($role, $result) {
            $roleCase = $role ?? fake()->randomElement(MembershipRole::cases());

            $resultValue = $result ?? fake()->randomElement([
                AuditLog::RESULT_STARTED,
                AuditLog::RESULT_STOPPED,
                AuditLog::RESULT_DENIED,
                AuditLog::RESULT_ROLE_CHANGED,
            ]);

            return [
                'action' => match ($resultValue) {
                    AuditLog::RESULT_STOPPED => AuditLog::ACTION_IMPERSONATION_STOPPED,
                    AuditLog::RESULT_DENIED => AuditLog::ACTION_IMPERSONATION_DENIED,
                    AuditLog::RESULT_ROLE_CHANGED => AuditLog::ACTION_IMPERSONATION_ROLE_CHANGED,
                    default => AuditLog::ACTION_IMPERSONATION_STARTED,
                },
                'subject_type' => 'impersonation-session',
                'subject_id' => (string) str()->ulid(),
                'properties' => [
                    'summary' => fake()->sentence(),
                    'impersonated_role' => $roleCase->value,
                    'justification' => fake()->sentence(8),
                    'result' => $resultValue,
                ],
            ];
        });
    }
}
