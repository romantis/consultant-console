<?php

namespace Database\Factories;

use App\Enums\ChangeProposalStatus;
use App\Models\ChangeProposal;
use App\Models\Engagement;
use App\Models\Membership;
use App\Models\Metric;
use App\Models\MetricParticipant;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<ChangeProposal>
 */
class ChangeProposalFactory extends Factory
{
    protected $model = ChangeProposal::class;

    public function definition(): array
    {
        return [
            'organization_id' => null,
            'engagement_id' => null,
            'metric_id' => Metric::factory(),
            'initiated_by' => null,
            'status' => ChangeProposalStatus::OPEN,
            'rationale' => $this->faker->sentence(12),
            'payload' => [
                'requested_changes' => [
                    $this->faker->sentence(8),
                    $this->faker->sentence(6),
                ],
                'notes' => $this->faker->sentence(10),
            ],
            'approver_ids' => [],
            'approved_at' => null,
            'merged_at' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (ChangeProposal $proposal) {
            $metric = $proposal->metric ?? ($proposal->metric_id ? Metric::query()->find($proposal->metric_id) : null);

            if ($metric) {
                $metric->loadMissing('engagement');

                if (! $proposal->organization_id && $metric->organization_id) {
                    $proposal->organization_id = $metric->organization_id;
                }

                if (! $proposal->engagement_id && $metric->engagement_id) {
                    $proposal->engagement_id = $metric->engagement_id;
                }
            }

            if (! $proposal->organization_id && $proposal->engagement_id) {
                $engagement = $proposal->engagement ?? Engagement::query()->find($proposal->engagement_id);
                if ($engagement && $engagement->organization_id) {
                    $proposal->organization_id = $engagement->organization_id;
                }
            }
        })->afterCreating(function (ChangeProposal $proposal) {
            if (! $proposal->initiated_by) {
                $organizationId = $proposal->organization_id
                    ?? $proposal->metric?->organization_id
                    ?? $proposal->engagement?->organization_id;

                $membership = Membership::factory()->create([
                    'organization_id' => $organizationId
                        ?? Organization::factory()->create()->getKey(),
                ]);

                $proposal->forceFill(['initiated_by' => $membership->getKey()])->save();
                $proposal->setRelation('initiator', $membership);
            } elseif ($proposal->initiator && $proposal->organization_id
                && $proposal->initiator->organization_id !== $proposal->organization_id) {
                $proposal->initiator->forceFill(['organization_id' => $proposal->organization_id])->save();
            }

            if (empty($proposal->approver_ids)) {
                $approver = MetricParticipant::factory()->approver()->create([
                    'metric_id' => $proposal->metric_id,
                ]);

                $proposal->forceFill([
                    'approver_ids' => [(string) $approver->getKey()],
                ])->save();
            }
        });
    }

    public function approved(?Carbon $approvedAt = null): self
    {
        return $this->state(function () use ($approvedAt) {
            $timestamp = $approvedAt ?? now();

            return [
                'status' => ChangeProposalStatus::APPROVED,
                'approved_at' => $timestamp,
                'merged_at' => null,
            ];
        });
    }

    public function rejected(): self
    {
        return $this->state(fn () => [
            'status' => ChangeProposalStatus::REJECTED,
            'approved_at' => null,
            'merged_at' => null,
        ]);
    }

    public function merged(?Carbon $mergedAt = null): self
    {
        return $this->state(function () use ($mergedAt) {
            $timestamp = $mergedAt ?? now();

            return [
                'status' => ChangeProposalStatus::MERGED,
                'approved_at' => (clone $timestamp)->subDay(),
                'merged_at' => $timestamp,
            ];
        });
    }
}
