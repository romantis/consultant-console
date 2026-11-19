<?php

namespace App\Http\Controllers;

use App\Actions\Metrics\BuildDeterministicFacilitationBrief;
use App\Actions\Metrics\BuildFacilitatorReadinessPack;
use App\Actions\Metrics\BuildMetricAlignmentBrief;
use App\Actions\Metrics\BuildMetricWorkshopReadiness;
use App\Actions\Metrics\DeriveMetricFacilitationPlan;
use App\Enums\EngagementStatus;
use App\Enums\MetricStatus;
use App\Models\Engagement;
use App\Models\Metric;
use App\Models\MetricFacilitationBrief;
use App\Models\Organization;
use App\Support\Metrics\Drafting\BriefFingerprint;
use App\Support\Metrics\Facilitation\FacilitationBriefDocument;
use App\Support\Metrics\FieldOptionLabelResolver;
use App\Support\Staff;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;

class MetricWorkshopPrepController extends Controller
{
    public function __construct(
        private readonly BuildMetricAlignmentBrief $buildMetricAlignmentBrief,
        private readonly BuildMetricWorkshopReadiness $buildMetricWorkshopReadiness,
        private readonly DeriveMetricFacilitationPlan $deriveMetricFacilitationPlan,
        private readonly BuildDeterministicFacilitationBrief $buildDeterministicFacilitationBrief,
        private readonly BuildFacilitatorReadinessPack $buildFacilitatorReadinessPack,
    ) {}

    public function __invoke(Request $request, Organization $organization, Engagement $engagement, Metric $metric): Response
    {
        $this->assertCanAccess($request, $organization, $engagement, $metric);
        $payload = $this->buildPrepPayload($metric);
        $currentFingerprint = BriefFingerprint::forFacilitationPlan(
            $payload['metric_alignment_brief'],
            $payload['workshop_readiness'],
        );

        $metric->loadMissing('facilitationBrief');

        $facilitationBrief = $metric->facilitationBrief
            ? $this->serializeFacilitationBrief($metric->facilitationBrief, $currentFingerprint)
            : null;

        // Private, operator-only preparation briefing. Deterministically derived
        // from the same payloads the page already renders — no provider call, no
        // persistence — and exposed only on this staff-gated prep route, never on
        // a client portal, handbook export, or live workshop participant surface.
        $facilitatorReadinessPack = $this->buildFacilitatorReadinessPack->handle(
            $payload['metric_alignment_brief'],
            $facilitationBrief,
            $payload['workshop_readiness'],
            $payload['field_roles'],
            (string) $metric->id,
            $metric->name,
        );

        return Inertia::render('Metrics/WorkshopPrep', [
            'organization' => [
                'id' => (string) $organization->id,
                'name' => $organization->name,
            ],
            'engagement' => [
                'id' => (string) $engagement->id,
                'title' => $engagement->title,
                'status' => $engagement->status instanceof EngagementStatus
                    ? $engagement->status->value
                    : (string) $engagement->status,
            ],
            'metric' => [
                'id' => (string) $metric->id,
                'name' => $metric->name,
                'status' => $metric->status instanceof MetricStatus
                    ? $metric->status->value
                    : (string) $metric->status,
                'key' => $metric->key,
                'field_roles' => $payload['field_roles'],
                'unclassified' => $payload['unclassified'],
            ],
            'fieldValueLabels' => $payload['field_value_labels'],
            'metric_alignment_brief' => $payload['metric_alignment_brief'],
            'facilitation_brief' => $facilitationBrief,
            'facilitator_readiness_pack' => $facilitatorReadinessPack,
            'engagement_metrics' => $engagement->metrics()
                ->select(['id', 'name', 'status'])
                ->orderBy('name')
                ->get()
                ->map(fn (Metric $m) => [
                    'id' => (string) $m->id,
                    'name' => $m->name,
                    'status' => $m->status instanceof MetricStatus
                        ? $m->status->value
                        : (string) $m->status,
                ]),
        ]);
    }

    public function storeFacilitation(
        Request $request,
        Organization $organization,
        Engagement $engagement,
        Metric $metric,
    ): JsonResponse {
        $this->assertCanAccess($request, $organization, $engagement, $metric);

        $payload = $this->buildPrepPayload($metric);
        $currentFingerprint = BriefFingerprint::forFacilitationPlan(
            $payload['metric_alignment_brief'],
            $payload['workshop_readiness'],
        );

        $metric->loadMissing('facilitationBrief');

        $regenerate = $request->boolean('regenerate');

        if ($metric->facilitationBrief && ! $regenerate) {
            return response()->json([
                'data' => $this->serializeFacilitationBrief($metric->facilitationBrief, $currentFingerprint),
            ]);
        }

        // Build the canonical agenda — one item per material conflict, missing
        // evidence gap, and decision to confirm — so the facilitation plan covers
        // every topic the prep page surfaces.
        $brief = $payload['metric_alignment_brief'];
        $agendaItems = [];
        foreach ([
            'material_conflicts' => 'must_resolve',
            'missing_evidence' => 'missing_evidence',
            'decision_needed' => 'needs_confirmation',
        ] as $bucket => $kind) {
            foreach ($brief[$bucket] ?? [] as $item) {
                $agendaItems[] = [
                    'id' => (string) ($item['id'] ?? ''),
                    'field_key' => (string) ($item['field_key'] ?? ''),
                    'field_label' => (string) ($item['field_label'] ?? ''),
                    'kind' => $kind,
                    'title' => (string) ($item['title'] ?? ''),
                    'rationale' => (string) ($item['rationale'] ?? ''),
                    'citation_ids' => array_values(array_column($item['source_refs'] ?? [], 'id')),
                ];
            }
        }

        // With no canonical conflict, missing evidence, or decision to confirm,
        // there is nothing to prepare.
        if ($agendaItems === []) {
            return response()->json([
                'error' => [
                    'code' => 'nothing_to_prepare',
                    'message' => 'There are no conflicts, missing evidence, or decisions to confirm for this metric yet.',
                ],
            ], 422);
        }

        $rawReadinessGaps = $payload['workshop_readiness']['gaps'] ?? [];

        // The plan is derived from the deterministic brief projection — the same
        // source-backed brief the prep page renders — never from a stored AI draft.
        $draftPayload = $this->buildDeterministicFacilitationBrief->build(
            $brief,
            (string) $metric->id,
            $metric->name,
        );

        $document = $this->deriveMetricFacilitationPlan->handle(
            $draftPayload,
            $currentFingerprint,
            null,
            $agendaItems,
            $rawReadinessGaps,
        );

        $this->validateDocument($document->toArray());

        // Explicit regeneration rebuilds the plan from the current decisions and
        // replaces the persisted run of show and outputs. It discards consultant
        // edits by design, so it is only reached on an explicit, confirmed
        // request (never silently) and uses the same optimistic-revision guard
        // as a save.
        if ($regenerate && $metric->facilitationBrief) {
            $result = DB::transaction(function () use ($metric, $request, $document, $currentFingerprint) {
                $brief = MetricFacilitationBrief::where('metric_id', $metric->id)
                    ->lockForUpdate()
                    ->first();

                if (! $brief) {
                    return 'not_found';
                }

                if ($brief->revision !== $request->integer('revision')) {
                    return 'conflict';
                }

                $brief->forceFill([
                    'status' => 'draft',
                    'source_fingerprint' => $currentFingerprint,
                    'generated_draft' => $document->sourceSnapshot['generated_draft'],
                    'document' => $document->toArray(),
                    'revision' => $brief->revision + 1,
                    'updated_by' => $request->user()?->id,
                ])->save();

                return $brief;
            });

            if ($result === 'not_found') {
                return response()->json([
                    'error' => [
                        'code' => 'facilitation_brief_missing',
                        'message' => 'Prepare the run of show before regenerating it.',
                    ],
                ], 404);
            }

            if ($result === 'conflict') {
                return response()->json([
                    'error' => [
                        'code' => 'stale_facilitation_revision',
                        'message' => 'This facilitation plan changed in another session. Reload before regenerating.',
                    ],
                ], 409);
            }

            return response()->json([
                'data' => $this->serializeFacilitationBrief($result, $currentFingerprint),
            ]);
        }

        try {
            $brief = $metric->facilitationBrief()->create([
                'schema_version' => '2.0',
                'status' => 'draft',
                'source_fingerprint' => $currentFingerprint,
                'generated_draft' => $document->sourceSnapshot['generated_draft'],
                'document' => $document->toArray(),
                'revision' => 1,
                'created_by' => $request->user()?->id,
                'updated_by' => $request->user()?->id,
            ]);
        } catch (UniqueConstraintViolationException $e) {
            $brief = $metric->facilitationBrief()->first();
        }

        return response()->json([
            'data' => $this->serializeFacilitationBrief($brief, $currentFingerprint),
        ], 201);
    }

    public function updateFacilitation(
        Request $request,
        Organization $organization,
        Engagement $engagement,
        Metric $metric,
    ): JsonResponse {
        $this->assertCanAccess($request, $organization, $engagement, $metric);

        $validated = $request->validate([
            'revision' => ['required', 'integer', 'min:1'],
            'status' => ['required', 'in:draft,ready_for_workshop'],
            'run_of_show' => ['present', 'array'],
            'run_of_show.*.id' => ['required', 'string', 'max:120'],
            'run_of_show.*.title' => ['required', 'string', 'max:180'],
            'run_of_show.*.duration_minutes' => ['required', 'integer', 'min:1', 'max:180'],
            'run_of_show.*.decision_ids' => ['present', 'array'],
            'run_of_show.*.decision_ids.*' => ['string', 'max:120'],
            'run_of_show.*.purpose.text' => ['required', 'string', 'max:1200'],
            'run_of_show.*.purpose.provenance' => ['required', 'in:system_suggested,consultant_authored'],
        ]);

        $payload = $this->buildPrepPayload($metric);
        $currentFingerprint = BriefFingerprint::forFacilitationPlan(
            $payload['metric_alignment_brief'],
            $payload['workshop_readiness'],
        );

        $result = DB::transaction(function () use ($metric, $validated) {
            $brief = MetricFacilitationBrief::where('metric_id', $metric->id)
                ->lockForUpdate()
                ->first();

            if (! $brief) {
                return 'not_found';
            }

            if ($brief->revision !== (int) $validated['revision']) {
                return 'conflict';
            }

            // Verify that all decision_ids exist in decisions_to_lock
            $validDecisionIds = array_column($brief->document['decisions_to_lock'] ?? [], 'id');
            foreach ($validated['run_of_show'] as $item) {
                foreach ($item['decision_ids'] ?? [] as $dId) {
                    if (! in_array($dId, $validDecisionIds, true)) {
                        throw ValidationException::withMessages([
                            'run_of_show' => ["Decision ID {$dId} is not valid."],
                        ]);
                    }
                }
            }

            // Provenance is server-authoritative: client-sent values are ignored,
            // consultant_authored is sticky, and any text change or new block
            // becomes consultant_authored.
            $originalRunOfShow = [];
            foreach ($brief->document['run_of_show'] ?? [] as $item) {
                if (isset($item['id'])) {
                    $originalRunOfShow[$item['id']] = $item;
                }
            }
            $validatedRunOfShow = [];
            foreach ($validated['run_of_show'] as $item) {
                $id = $item['id'] ?? '';
                $original = $originalRunOfShow[$id] ?? null;
                $item['purpose']['provenance'] = $this->resolveProvenance(
                    $original['purpose'] ?? null,
                    $item['purpose']['text'] ?? '',
                );
                $validatedRunOfShow[] = $item;
            }

            // expected_outputs is hidden from the UI (T027) and server-authoritative
            // (T060): the persisted value carried on $brief->document is preserved as
            // is, and any client-sent copy is ignored, so a stale page can no longer
            // clobber a concurrent change. The frozen schema 1.0 field stays in place.
            $docArray = $brief->document ?? [];
            $docArray['status'] = $validated['status'];
            $docArray['run_of_show'] = $validatedRunOfShow;

            $document = FacilitationBriefDocument::fromArray($docArray);
            $this->validateDocument($document->toArray());

            $brief->forceFill([
                'status' => $validated['status'],
                'document' => $document->toArray(),
                'revision' => $brief->revision + 1,
                'updated_by' => request()->user()?->id,
            ])->save();

            return $brief;
        });

        if ($result === 'not_found') {
            return response()->json([
                'error' => [
                    'code' => 'facilitation_brief_missing',
                    'message' => 'Prepare the run of show before saving facilitation edits.',
                ],
            ], 404);
        }

        if ($result === 'conflict') {
            return response()->json([
                'error' => [
                    'code' => 'stale_facilitation_revision',
                    'message' => 'This facilitation plan changed in another session. Reload before saving again.',
                ],
            ], 409);
        }

        return response()->json([
            'data' => $this->serializeFacilitationBrief($result, $currentFingerprint),
        ]);
    }

    /**
     * @return array{
     *     id: string,
     *     status: string,
     *     schema_version: string,
     *     source_fingerprint: string,
     *     revision: int,
     *     needs_source_review: bool,
     *     document: array<string, mixed>
     * }
     */
    private function serializeFacilitationBrief(MetricFacilitationBrief $brief, string $currentFingerprint): array
    {
        // The plan is a deterministic projection of the canonical brief, so it is
        // stale exactly when the brief's source fingerprint has moved.
        $needsSourceReview = $brief->source_fingerprint !== $currentFingerprint;
        $status = $needsSourceReview ? 'needs_source_review' : $brief->status;

        $docArray = $brief->document ?? [];
        $docArray['status'] = $status;

        return [
            'id' => (string) $brief->id,
            'status' => $status,
            'schema_version' => (string) $brief->schema_version,
            'source_fingerprint' => (string) $brief->source_fingerprint,
            'revision' => $brief->revision,
            'needs_source_review' => $needsSourceReview,
            'document' => FacilitationBriefDocument::fromArray($docArray)->toArray(),
        ];
    }

    /**
     * Determines the stored provenance for an editable content block. Once a
     * block is consultant_authored it can never revert to system_suggested, and
     * any text change (or a newly added block) makes it consultant_authored.
     *
     * @param  array<string, mixed>|null  $originalBlock
     */
    private function resolveProvenance(?array $originalBlock, string $submittedText): string
    {
        if ($originalBlock === null) {
            return 'consultant_authored';
        }

        $originalProvenance = (string) ($originalBlock['provenance'] ?? 'system_suggested');

        if ($originalProvenance === 'consultant_authored') {
            return 'consultant_authored';
        }

        if ($submittedText !== (string) ($originalBlock['text'] ?? '')) {
            return 'consultant_authored';
        }

        return $originalProvenance;
    }

    private function validateDocument(array $documentArray): void
    {
        $schemaPath = base_path('specs/034-optymize-prompt/contracts/consultant-facilitation-brief.schema.json');
        if (! file_exists($schemaPath)) {
            throw new \RuntimeException(
                'Consultant facilitation brief schema is missing from the deployment artifact: '.$schemaPath,
            );
        }

        $schema = json_decode(file_get_contents($schemaPath), flags: JSON_THROW_ON_ERROR);
        $data = json_decode(json_encode($documentArray, JSON_THROW_ON_ERROR), flags: JSON_THROW_ON_ERROR);

        $validator = new Validator;
        $result = $validator->validate($data, $schema);

        if (! $result->isValid()) {
            $error = $result->error();
            $formatter = new ErrorFormatter;
            $formatted = $formatter->formatFlat($error);

            throw ValidationException::withMessages([
                'document' => ['The facilitation document is invalid: '.implode('; ', $formatted)],
            ]);
        }
    }

    private function assertCanAccess(
        Request $request,
        Organization $organization,
        Engagement $engagement,
        Metric $metric,
    ): void {
        if (
            $metric->organization_id !== $organization->id
            || $engagement->organization_id !== $organization->id
            || $metric->engagement_id !== $engagement->id
        ) {
            abort(404);
        }

        $this->authorize('view', $metric);

        $user = $request->user();

        if (! $user || ! Staff::isInternalStaff($user)) {
            abort(403);
        }
    }

    /**
     * @return array{
     *     field_roles: array<int, array<string, mixed>>,
     *     unclassified: array<int, string>,
     *     field_value_labels: array<string, array<string, string>>,
     *     metric_alignment_brief: array<string, mixed>,
     *     workshop_readiness: array<string, mixed>
     * }
     */
    private function buildPrepPayload(Metric $metric): array
    {
        $metric->load([
            'engagement',
            'definitions' => function ($query) {
                $query->where('status', 'Submitted')->with('user');
            },
            'template',
            'discrepancies',
        ]);

        $rolesPayload = $this->buildMetricAlignmentBrief->resolveFieldRolesAndUnclassified($metric);
        $fieldRoles = $rolesPayload['field_roles'];

        return [
            'field_roles' => $fieldRoles,
            'unclassified' => $rolesPayload['unclassified'],
            'field_value_labels' => FieldOptionLabelResolver::forMetric($metric),
            'metric_alignment_brief' => $this->buildMetricAlignmentBrief->handle(
                $metric,
                $fieldRoles,
            ),
            'workshop_readiness' => $this->buildMetricWorkshopReadiness
                ->handle($metric)
                ->toArray(),
        ];
    }
}
