<?php

namespace App\Actions\Metrics;

use App\Actions\Audit\LogChangeProposalEvent;
use App\Enums\ChangeProposalStatus;
use App\Enums\MetricStatus;
use App\Jobs\NotifyChangeProposalApprovers;
use App\Models\ChangeProposal;
use App\Models\FinalDefinition;
use App\Models\Membership;
use App\Models\Metric;
use App\Models\MetricParticipant;
use App\Models\User;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OpenChangeProposal
{
    public function __construct(
        private LogChangeProposalEvent $logChangeProposalEvent
    ) {}

    /**
     * @param  array{rationale?:string|null, payload?:Arrayable|array|null, approver_ids?:array<int, string>|string|null}  $payload
     * @return array{id:string, metric_id:string, engagement_id:string, organization_id:string, status:string, rationale:string, payload:array, approvers:array<int, array{id:string, user_id:string, name:?string, email:?string}>, final_definition_version:int}
     *
     * @throws ValidationException
     */
    public function handle(Metric $metric, array $payload, ?User $actor = null): array
    {
        $this->assertMetricIsFinalized($metric);

        $finalDefinition = $this->requireSignedOffDefinition($metric);
        $organizationId = (string) ($metric->organization_id ?? $metric->engagement?->organization_id ?? '');

        if ($organizationId === '' || empty($metric->engagement_id)) {
            throw ValidationException::withMessages([
                'metric_id' => __('The metric is not associated with a valid engagement or organization.'),
            ]);
        }

        $rationale = $this->sanitizeRationale($payload['rationale'] ?? null);
        $definitionPayload = $this->normalizePayload($payload['payload'] ?? null);
        $approverIds = $this->normalizeApproverIds($payload['approver_ids'] ?? []);
        $approvers = $this->resolveApprovers($metric, $approverIds);
        $initiatorMembershipId = $this->resolveMembershipId($metric, $actor);

        /** @var ChangeProposal|null $proposal */
        $proposal = null;

        DB::transaction(function () use (&$proposal, $metric, $organizationId, $rationale, $definitionPayload, $approverIds, $actor, $finalDefinition, $initiatorMembershipId) {
            $proposal = ChangeProposal::create([
                'organization_id' => $organizationId,
                'engagement_id' => $metric->engagement_id,
                'metric_id' => $metric->getKey(),
                'initiated_by' => $initiatorMembershipId,
                'status' => ChangeProposalStatus::OPEN,
                'rationale' => $rationale,
                'payload' => $definitionPayload,
                'approver_ids' => $approverIds->all(),
            ]);

            $this->logChangeProposalEvent->handle(
                $proposal,
                'metric.change-proposal.opened',
                array_filter([
                    'approver_ids' => $approverIds->all(),
                    'final_definition_version' => $finalDefinition->version,
                ]),
                $actor,
                $proposal
            );
        });

        $proposal?->refresh();

        if (! $proposal) {
            throw ValidationException::withMessages([
                'metric_id' => __('Unable to open a change proposal at this time.'),
            ]);
        }

        NotifyChangeProposalApprovers::dispatch(
            (string) $proposal->getKey(),
            (string) $proposal->organization_id,
            (string) $proposal->engagement_id,
            (string) $proposal->metric_id,
            $actor?->getKey()
        )->afterCommit();

        return [
            'id' => (string) $proposal->getKey(),
            'metric_id' => (string) $metric->getKey(),
            'engagement_id' => (string) $metric->engagement_id,
            'organization_id' => $organizationId,
            'status' => $proposal->status instanceof ChangeProposalStatus
                ? $proposal->status->value
                : (string) $proposal->status,
            'rationale' => $proposal->rationale,
            'payload' => $proposal->payload ?? [],
            'approvers' => $approvers
                ->map(fn (MetricParticipant $participant) => [
                    'id' => (string) $participant->getKey(),
                    'user_id' => (string) $participant->user_id,
                    'name' => $participant->user?->name,
                    'email' => $participant->user?->email,
                ])
                ->values()
                ->all(),
            'final_definition_version' => (int) $finalDefinition->version,
        ];
    }

    private function assertMetricIsFinalized(Metric $metric): void
    {
        $status = $metric->status instanceof MetricStatus
            ? $metric->status
            : ($metric->status ? MetricStatus::from($metric->status) : null);

        if ($status !== MetricStatus::FINALIZED) {
            throw ValidationException::withMessages([
                'metric_id' => __('Only finalized metrics can accept change proposals.'),
            ]);
        }
    }

    private function requireSignedOffDefinition(Metric $metric): FinalDefinition
    {
        $finalDefinition = $metric->finalDefinition;

        if (! $finalDefinition || ! $finalDefinition->signed_off_at) {
            throw ValidationException::withMessages([
                'metric_id' => __('Finalize and sign off the metric before opening a change proposal.'),
            ]);
        }

        return $finalDefinition;
    }

    private function sanitizeRationale(?string $rationale): string
    {
        $value = trim((string) ($rationale ?? ''));

        if ($value === '') {
            throw ValidationException::withMessages([
                'rationale' => __('Provide a rationale for the proposed change.'),
            ]);
        }

        return Str::limit($value, 2000);
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private function normalizePayload(Arrayable|array|string|null $payload): array
    {
        if ($payload instanceof Arrayable) {
            $payload = $payload->toArray();
        }

        if (! is_array($payload)) {
            $payload = [];
        }

        $clean = collect($payload)
            ->reject(fn ($value, $key) => $key === '')
            ->all();

        if (empty($clean)) {
            throw ValidationException::withMessages([
                'payload' => __('Provide at least one field describing the proposed changes.'),
            ]);
        }

        return $clean;
    }

    /**
     * @param  array<int, string>|string|null  $approverIds
     */
    private function normalizeApproverIds(array|string|null $approverIds): Collection
    {
        $values = is_array($approverIds) ? $approverIds : (array) $approverIds;

        return collect($values)
            ->map(fn ($value) => (string) $value)
            ->filter(fn ($value) => $value !== '')
            ->unique()
            ->values();
    }

    /**
     * @return Collection<string, MetricParticipant>
     *
     * @throws ValidationException
     */
    private function resolveApprovers(Metric $metric, Collection $approverIds): Collection
    {
        if ($approverIds->isEmpty()) {
            throw ValidationException::withMessages([
                'approver_ids' => __('Select at least one approver for this change proposal.'),
            ]);
        }

        $approvers = MetricParticipant::query()
            ->with('user:id,name,email')
            ->where('metric_id', $metric->getKey())
            ->where('responsibility', 'approver')
            ->whereIn('id', $approverIds->all())
            ->get()
            ->keyBy(fn (MetricParticipant $participant) => (string) $participant->getKey());

        $missing = $approverIds->diff($approvers->keys());
        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages([
                'approver_ids' => __('One or more approvers are invalid for this metric.'),
            ]);
        }

        $withoutUsers = $approvers->filter(fn (MetricParticipant $participant) => empty($participant->user_id));
        if ($withoutUsers->isNotEmpty()) {
            throw ValidationException::withMessages([
                'approver_ids' => __('Ensure every approver belongs to an active user.'),
            ]);
        }

        return $approvers;
    }

    private function resolveMembershipId(Metric $metric, ?User $actor): ?string
    {
        if (! $actor) {
            return null;
        }

        /** @var string|null $membershipId */
        $membershipId = Membership::query()
            ->where('organization_id', $metric->organization_id ?? $metric->engagement?->organization_id)
            ->where('user_id', $actor->getKey())
            ->value('id');

        return $membershipId ? (string) $membershipId : null;
    }
}
