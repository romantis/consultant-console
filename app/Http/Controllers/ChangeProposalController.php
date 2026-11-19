<?php

namespace App\Http\Controllers;

use App\Actions\Audit\LogChangeProposalEvent;
use App\Actions\Metrics\MergeChangeProposal;
use App\Actions\Metrics\OpenChangeProposal;
use App\Enums\ChangeProposalStatus;
use App\Models\ChangeProposal;
use App\Models\Metric;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ChangeProposalController extends Controller
{
    public function store(Request $request, Organization $organization, Metric $metric, OpenChangeProposal $openChangeProposal): JsonResponse|RedirectResponse
    {
        $this->ensureFeatureEnabled();
        $this->ensureMetricContext($request, $metric);
        $this->authorize('requestChange', $metric);

        $validated = $request->validate([
            'rationale' => ['required', 'string', 'max:2000'],
            'payload' => ['required', 'array'],
            'approver_ids' => ['required', 'array', 'min:1'],
            'approver_ids.*' => ['string'],
        ]);

        $result = $openChangeProposal->handle(
            $metric,
            Arr::only($validated, ['rationale', 'payload', 'approver_ids']),
            $request->user()
        );

        return $this->respond(
            $request,
            $result,
            201,
            __('Change proposal submitted for approval.')
        );
    }

    public function decide(Request $request, Organization $organization, ChangeProposal $proposal, LogChangeProposalEvent $logChangeProposalEvent): JsonResponse|RedirectResponse
    {
        $this->ensureFeatureEnabled();
        $proposal->loadMissing('metric');
        $metric = $proposal->metric;

        abort_if(! $metric, 404);
        $this->ensureProposalContext($request, $proposal);

        /** @var User $actor */
        $actor = $request->user();

        $this->authorize('decide', $proposal);

        $validated = $request->validate([
            'decision' => ['required', 'string', Rule::in(['approve', 'reject'])],
            'comment' => ['nullable', 'string'],
        ]);

        $decision = $validated['decision'];
        $comment = $this->sanitizeComment($validated['comment'] ?? null);
        $status = $decision === 'approve'
            ? ChangeProposalStatus::APPROVED
            : ChangeProposalStatus::REJECTED;
        $decisionAt = now();

        DB::transaction(function () use ($proposal, $logChangeProposalEvent, $status, $decisionAt, $decision, $comment, $actor) {
            $proposal->forceFill([
                'status' => $status->value,
                'approved_at' => $status === ChangeProposalStatus::APPROVED ? $decisionAt : null,
            ])->save();

            $logChangeProposalEvent->handle(
                $proposal,
                'metric.change-proposal.decision',
                array_filter([
                    'decision' => $decision,
                    'comment' => $comment,
                    'approved_at' => $status === ChangeProposalStatus::APPROVED
                        ? $decisionAt->copy()->toIso8601String()
                        : null,
                ]),
                $actor,
                $proposal
            );
        });

        $proposal->refresh();

        $payload = [
            'id' => (string) $proposal->getKey(),
            'status' => $proposal->status instanceof ChangeProposalStatus
                ? $proposal->status->value
                : (string) $proposal->status,
            'decision' => $decision,
            'comment' => $comment,
            'approved_at' => optional($proposal->approved_at)->toIso8601String(),
        ];

        $flashMessage = $decision === 'approve'
            ? __('Change proposal approved.')
            : __('Requested changes for this proposal.');

        return $this->respond($request, $payload, 200, $flashMessage);
    }

    public function merge(
        Request $request,
        Organization $organization,
        ChangeProposal $proposal,
        MergeChangeProposal $mergeChangeProposal
    ): JsonResponse|RedirectResponse {
        $this->ensureFeatureEnabled();
        $proposal->loadMissing('metric');
        $metric = $proposal->metric;

        abort_if(! $metric, 404);
        $this->ensureProposalContext($request, $proposal);

        $this->authorize('merge', $proposal);

        $result = $mergeChangeProposal->handle($proposal, $request->user());

        return $this->respond(
            $request,
            $result,
            200,
            __('Change proposal merged into the latest final definition.')
        );
    }

    private function sanitizeComment(?string $comment): ?string
    {
        if ($comment === null) {
            return null;
        }

        $comment = trim($comment);

        return $comment === '' ? null : Str::limit($comment, 2000);
    }

    private function ensureFeatureEnabled(): void
    {
        abort_if(! config('metric-harmony.features.change_proposals', false), 404);
    }

    private function ensureMetricContext(Request $request, Metric $metric): void
    {
        $organization = $request->route('organization');

        if (! $organization) {
            return;
        }

        $organizationId = $organization instanceof Organization
            ? $organization->id
            : (string) $organization;

        abort_if((string) $metric->organization_id !== (string) $organizationId, 404);
    }

    private function ensureProposalContext(Request $request, ChangeProposal $proposal): void
    {
        $organization = $request->route('organization');

        if (! $organization) {
            return;
        }

        $organizationId = $organization instanceof Organization
            ? $organization->id
            : (string) $organization;

        abort_if((string) $proposal->organization_id !== (string) $organizationId, 404);
    }

    /**
     * @param  array<string,mixed>  $data
     */
    private function respond(Request $request, array $data, int $status = 200, ?string $flash = null): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json($data, $status);
        }

        $redirect = back();

        if ($flash) {
            $redirect = $redirect->with('success', $flash);
        }

        return $redirect;
    }
}
