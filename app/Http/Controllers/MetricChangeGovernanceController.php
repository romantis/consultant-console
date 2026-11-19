<?php

namespace App\Http\Controllers;

use App\Enums\ChangeProposalStatus;
use App\Models\ChangeProposal;
use App\Models\Metric;
use App\Models\MetricParticipant;
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class MetricChangeGovernanceController extends Controller
{
    private const PROPOSAL_PLACEHOLDER = '__PROPOSAL__';

    public function __invoke(Request $request, Organization $organization, Metric $metric): Response
    {
        abort_unless((string) $metric->organization_id === (string) $organization->id, 404);

        $this->authorize('view', $metric);

        $metric->loadMissing([
            'finalDefinition',
            'changeProposals.initiator.user',
        ]);

        $finalDefinition = $metric->finalDefinition;
        $definitionFields = $this->prepareDefinitionFields($finalDefinition?->definition_json ?? []);
        $definitionLookup = collect($definitionFields)->keyBy('key');

        $approverParticipants = $metric->participants()
            ->with('user:id,name,email')
            ->where('responsibility', 'approver')
            ->get();

        $user = $request->user();

        $proposals = $metric->changeProposals()
            ->orderByDesc('created_at')
            ->get()
            ->map(function (ChangeProposal $proposal) use ($definitionLookup, $approverParticipants, $user) {
                $diff = $this->buildDiff(
                    $proposal->payload ?? [],
                    $definitionLookup
                );

                $approvals = $this->buildApprovals(
                    $proposal,
                    $approverParticipants
                );

                $status = $proposal->status instanceof ChangeProposalStatus
                    ? $proposal->status
                    : ChangeProposalStatus::from((string) $proposal->status);

                $canDecide = $user
                    ? Gate::forUser($user)->allows('decide', $proposal)
                    : false;

                $canMerge = $user
                    ? Gate::forUser($user)->allows('merge', $proposal)
                    : false;

                return [
                    'id' => (string) $proposal->getKey(),
                    'status' => $status->value,
                    'rationale' => $proposal->rationale,
                    'opened_at' => optional($proposal->created_at)->toIso8601String(),
                    'opened_by' => [
                        'name' => $proposal->initiator?->user?->name,
                        'role' => null,
                    ],
                    'diff' => $diff,
                    'approvals' => $approvals,
                    'actions' => [
                        'decision_required' => $status === ChangeProposalStatus::OPEN && $canDecide,
                        'can_decide' => $canDecide,
                        'can_merge' => $status === ChangeProposalStatus::APPROVED && $canMerge,
                    ],
                ];
            })
            ->values();

        $stats = [
            'open' => $proposals->where('status', ChangeProposalStatus::OPEN->value)->count(),
            'approved' => $proposals->where('status', ChangeProposalStatus::APPROVED->value)->count(),
            'merged' => $proposals->where('status', ChangeProposalStatus::MERGED->value)->count(),
            'rejected' => $proposals->where('status', ChangeProposalStatus::REJECTED->value)->count(),
        ];

        $permissions = [
            'canOpen' => $user ? Gate::forUser($user)->allows('requestChange', $metric) : false,
            'canMerge' => $user ? Gate::forUser($user)->allows('review', $metric) : false,
        ];

        $endpoints = [
            'store' => route('org.metrics.change-proposals.store', [
                'organization' => $organization,
                'metric' => $metric,
            ]),
            'decision' => route('org.change-proposals.decision', [
                'organization' => $organization,
                'proposal' => self::PROPOSAL_PLACEHOLDER,
            ]),
            'merge' => route('org.change-proposals.merge', [
                'organization' => $organization,
                'proposal' => self::PROPOSAL_PLACEHOLDER,
            ]),
        ];

        return Inertia::render('Metrics/ChangeGovernance', [
            'metric' => [
                'id' => (string) $metric->getKey(),
                'name' => $metric->name,
                'status' => $metric->status?->value ?? (string) $metric->status,
                'slug' => $metric->slug,
            ],
            'finalDefinition' => [
                'version' => $finalDefinition->version ?? 1,
                'fields' => $definitionFields,
            ],
            'changeProposals' => $proposals,
            'stats' => $stats,
            'permissions' => $permissions,
            'endpoints' => $endpoints,
            'approverOptions' => $approverParticipants
                ->map(fn (MetricParticipant $participant) => [
                    'id' => (string) $participant->getKey(),
                    'name' => $participant->user?->name ?? __('Unassigned'),
                    'email' => $participant->user?->email,
                ])
                ->values(),
        ]);
    }

    /**
     * @param  array<string, array<string, mixed>>  $definition
     * @return array<int, array{key:string,label:string,value:mixed,type:?string}>
     */
    private function prepareDefinitionFields(array $definition): array
    {
        return collect($definition)
            ->map(fn ($field, $key) => [
                'key' => (string) $key,
                'label' => $field['label'] ?? Str::headline((string) $key),
                'value' => $field['value'] ?? null,
                'type' => $field['type'] ?? null,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, array<string, mixed>>  $payload
     * @param  Collection<string, array{key:string,label:string,value:mixed,type:?string}>  $definitionLookup
     * @return array<int, array{field:string,label:string,before:mixed,after:mixed}>
     */
    private function buildDiff(array $payload, Collection $definitionLookup): array
    {
        return collect($payload)
            ->map(function ($candidate, $field) use ($definitionLookup) {
                $existing = $definitionLookup->get((string) $field, [
                    'label' => Str::headline((string) $field),
                    'value' => null,
                ]);

                return [
                    'field' => (string) $field,
                    'label' => $candidate['label'] ?? $existing['label'] ?? Str::headline((string) $field),
                    'before' => $existing['value'] ?? null,
                    'after' => $candidate['value'] ?? null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, MetricParticipant>  $participants
     * @return array<int, array{id:string,name:?string,email:?string,status:string,decided_at:?string}>
     */
    private function buildApprovals(ChangeProposal $proposal, Collection $participants): array
    {
        $status = $proposal->status instanceof ChangeProposalStatus
            ? $proposal->status
            : ChangeProposalStatus::from((string) $proposal->status);

        $participantMap = $participants->keyBy(fn (MetricParticipant $participant) => (string) $participant->getKey());

        $approvalStatus = match ($status) {
            ChangeProposalStatus::APPROVED => 'approved',
            ChangeProposalStatus::REJECTED => 'rejected',
            ChangeProposalStatus::MERGED => 'approved',
            default => 'pending',
        };

        return collect($proposal->approver_ids ?? [])
            ->map(fn ($id) => $participantMap->get((string) $id))
            ->filter()
            ->map(fn (MetricParticipant $participant) => [
                'id' => (string) $participant->getKey(),
                'name' => $participant->user?->name ?? __('Approver'),
                'email' => $participant->user?->email,
                'status' => $approvalStatus,
                'decided_at' => $proposal->approved_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }
}
