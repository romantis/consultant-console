<?php

namespace App\Actions\Metrics;

use App\Actions\Audit\LogChangeProposalEvent;
use App\Actions\OrgMetrics\CreateMergedOrgFinalDefinition;
use App\Enums\ChangeProposalStatus;
use App\Models\ChangeProposal;
use App\Models\FinalDefinition;
use App\Models\Metric;
use App\Models\OrgFinalDefinition;
use App\Models\User;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MergeChangeProposal
{
    public function __construct(
        private LogChangeProposalEvent $logChangeProposalEvent,
        private CreateMergedOrgFinalDefinition $createMergedOrgFinalDefinition
    ) {}

    /**
     * @return array{
     *     id: string,
     *     status: string,
     *     metric_id: string,
     *     engagement_id: string,
     *     organization_id: string,
     *     final_definition_version: int,
     *     final_definition: array{
     *         id: string,
     *         version: int,
     *         definition_json: array,
     *         notes: ?string,
     *         signed_off_at: ?string,
     *     }
     * }
     *
     * @throws ValidationException
     */
    public function handle(ChangeProposal $proposal, ?User $actor = null): array
    {
        $metric = $proposal->metric;

        if (! $metric) {
            throw ValidationException::withMessages([
                'proposal_id' => __('Unable to locate the metric for this change proposal.'),
            ]);
        }

        $this->assertMergeable($proposal);

        $finalDefinition = $this->requireSignedOffDefinition($metric);
        $nextVersion = ((int) ($finalDefinition->version ?? 1)) + 1;
        $payload = $this->normalizePayload($proposal->payload ?? []);
        $hasImplementationGapsOverride = array_key_exists('implementation_gaps', $payload);
        $implementationGaps = $this->extractImplementationGapsOverride($payload);
        $this->stripUnsupportedSignalPayload($payload);
        $notes = $this->extractNotes($payload, $finalDefinition->notes);
        $definitionFields = $this->mergeDefinitionFields(
            $finalDefinition->definition_json ?? [],
            $payload
        );

        $mergedAt = now();

        /** @var FinalDefinition|null $newDefinition */
        $newDefinition = null;
        /** @var OrgFinalDefinition|null $newOrgDefinition */
        $newOrgDefinition = null;

        DB::transaction(function () use (
            &$newDefinition,
            &$newOrgDefinition,
            $proposal,
            $metric,
            $actor,
            $definitionFields,
            $notes,
            $nextVersion,
            $mergedAt,
            $finalDefinition,
            $hasImplementationGapsOverride,
            $implementationGaps
        ) {
            $newDefinition = FinalDefinition::create([
                'metric_id' => $metric->getKey(),
                'user_id' => $actor?->getKey() ?? $proposal->initiated_by ?? $finalDefinition->user_id,
                'version' => $nextVersion,
                'status' => $finalDefinition->status ?? 'Finalized',
                'definition_json' => $definitionFields,
                'notes' => $notes,
                'operational_status' => null,
                'operational_status_notes' => null,
                'alignment_summary' => null,
                'implementation_gaps' => $hasImplementationGapsOverride ? $implementationGaps : null,
                'validation_steps' => null,
                'common_mismatch_causes' => null,
                'owner_participant_id' => $finalDefinition->owner_participant_id,
                'signed_off_at' => $proposal->approved_at ?? $finalDefinition->signed_off_at ?? $mergedAt,
                'signed_off_by' => $finalDefinition->signed_off_by,
            ]);

            $newOrgDefinition = $this->createMergedOrgFinalDefinition->handle(
                $metric,
                $newDefinition,
                $hasImplementationGapsOverride
            );

            $proposal->forceFill([
                'status' => ChangeProposalStatus::MERGED->value,
                'merged_at' => $mergedAt,
            ])->save();

            $this->logChangeProposalEvent->handle(
                $proposal,
                'metric.change-proposal.merged',
                array_filter([
                    'final_definition_id' => (string) $newDefinition->getKey(),
                    'org_final_definition_id' => $newOrgDefinition ? (string) $newOrgDefinition->getKey() : null,
                    'final_definition_version' => $nextVersion,
                    'merged_at' => $mergedAt->copy()->toIso8601String(),
                ]),
                $actor,
                $proposal
            );
        });

        if (! $newDefinition) {
            throw ValidationException::withMessages([
                'proposal_id' => __('Unable to merge the change proposal at this time.'),
            ]);
        }

        $proposal->refresh();

        return [
            'id' => (string) $proposal->getKey(),
            'status' => $proposal->status instanceof ChangeProposalStatus
                ? $proposal->status->value
                : (string) $proposal->status,
            'metric_id' => (string) $proposal->metric_id,
            'engagement_id' => (string) $proposal->engagement_id,
            'organization_id' => (string) $proposal->organization_id,
            'final_definition_version' => $nextVersion,
            'final_definition' => [
                'id' => (string) $newDefinition->getKey(),
                'version' => $nextVersion,
                'definition_json' => $newDefinition->definition_json ?? [],
                'notes' => $newDefinition->notes,
                'signed_off_at' => optional($newDefinition->signed_off_at)->toIso8601String(),
            ],
        ];
    }

    private function assertMergeable(ChangeProposal $proposal): void
    {
        $status = $proposal->status instanceof ChangeProposalStatus
            ? $proposal->status
            : ChangeProposalStatus::from((string) $proposal->status);

        if ($status !== ChangeProposalStatus::APPROVED) {
            throw ValidationException::withMessages([
                'status' => __('Only approved change proposals can be merged.'),
            ]);
        }
    }

    private function requireSignedOffDefinition(Metric $metric): FinalDefinition
    {
        $finalDefinition = $metric->finalDefinition;

        if (! $finalDefinition || ! $finalDefinition->signed_off_at) {
            throw ValidationException::withMessages([
                'metric_id' => __('A signed-off final definition is required before merging changes.'),
            ]);
        }

        return $finalDefinition;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizePayload(Arrayable|array|string|null $payload): array
    {
        if ($payload instanceof Arrayable) {
            $payload = $payload->toArray();
        }

        if (! is_array($payload)) {
            return [];
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    private function extractImplementationGapsOverride(array &$payload): ?array
    {
        if (! array_key_exists('implementation_gaps', $payload)) {
            return null;
        }

        $implementationGaps = $payload['implementation_gaps'];
        unset($payload['implementation_gaps']);

        if ($implementationGaps === null) {
            return null;
        }

        if (! is_array($implementationGaps)) {
            throw ValidationException::withMessages([
                'payload.implementation_gaps' => __('Implementation gaps override must be an object or null.'),
            ]);
        }

        return $implementationGaps;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function stripUnsupportedSignalPayload(array &$payload): void
    {
        unset(
            $payload['alignment_summary'],
            $payload['operational_status'],
            $payload['operational_status_notes']
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractNotes(array &$payload, ?string $fallback): ?string
    {
        if (! array_key_exists('notes', $payload)) {
            return $fallback;
        }

        $notes = $payload['notes'];
        unset($payload['notes']);

        if ($notes === null) {
            return null;
        }

        $notes = Str::squish((string) $notes);

        return $notes === '' ? null : Str::limit($notes, 2000);
    }

    /**
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function mergeDefinitionFields(array $current, array $payload): array
    {
        $merged = collect($current)
            ->mapWithKeys(function ($field, $key) {
                $label = Arr::get($field, 'label', Str::headline((string) $key));

                return [$key => [
                    'label' => $label,
                    'value' => Arr::get($field, 'value'),
                    'unit' => Arr::get($field, 'unit'),
                    'notes' => Arr::get($field, 'notes'),
                    'companion_of' => Arr::get($field, 'companion_of'),
                ]];
            });

        foreach ($payload as $key => $value) {
            if (! is_array($value)) {
                continue;
            }

            $existing = $merged->get($key, [
                'label' => Str::headline((string) $key),
                'value' => null,
                'unit' => null,
                'notes' => null,
                'companion_of' => null,
            ]);

            $merged->put($key, [
                'label' => array_key_exists('label', $value)
                    ? $value['label']
                    : ($existing['label'] ?? Str::headline((string) $key)),
                'value' => $value['value'] ?? ($existing['value'] ?? null),
                'unit' => $value['unit'] ?? ($existing['unit'] ?? null),
                'notes' => $value['notes'] ?? ($existing['notes'] ?? null),
                'companion_of' => $value['companion_of'] ?? ($existing['companion_of'] ?? null),
            ]);
        }

        return $merged->all();
    }
}
