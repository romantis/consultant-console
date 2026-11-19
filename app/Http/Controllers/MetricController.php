<?php

namespace App\Http\Controllers;

use App\Enums\EngagementStatus;
use App\Enums\MetricStatus;
use App\Models\FinalDefinition;
use App\Models\Metric;
use App\Models\Organization;
use App\Support\Metrics\FieldOptionLabelResolver;
use App\Support\Metrics\FieldRoleSchema;
use App\Support\Staff;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class MetricController extends Controller
{
    public function show(Request $request, Organization $organization, Metric $metric): Response|RedirectResponse
    {
        if ($metric->organization_id !== $organization->id) {
            abort(404);
        }

        $this->authorize('view', $metric);

        $metric->loadMissing('engagement');
        $engagement = $metric->engagement;

        $requestedEngagementId = $request->string('engagement')->trim();
        if ($requestedEngagementId->isNotEmpty()) {
            abort_if(! $engagement || (string) $engagement->id !== $requestedEngagementId->toString(), 404);
        }

        if (! $engagement) {
            abort(404);
        }

        $user = $request->user();
        if ($user && ! Staff::isInternalStaff($user)) {
            $status = $engagement->status instanceof EngagementStatus
                ? $engagement->status
                : EngagementStatus::tryFromLegacy((string) $engagement->status);

            if ($status !== EngagementStatus::IN_WORKSHOP) {
                abort(403, 'Workshop access is available once the engagement is in workshop.');
            }
        }

        // Redirect to the workshop view; legacy metric payload below will be removed in follow-up.
        return redirect()->route('org.engagements.workshop', [
            $organization,
            $engagement,
            'metric' => $metric->getKey(),
        ]);

        $discrepanciesByField = $metric->discrepancies->keyBy('field_key');

        $matrix = [];
        $metaMatrix = [];
        $optionLabelMap = FieldOptionLabelResolver::forMetric($metric);

        foreach ($metric->definitions as $definition) {
            foreach ($definition->normalizedFields() as $fieldKey => $field) {
                $matrix[$fieldKey]['label'] = $field['label'] ?? Str::headline($fieldKey);
                $matrix[$fieldKey]['type'] = $field['type'] ?? 'text';
                $matrix[$fieldKey]['options'] = $optionLabelMap[$fieldKey] ?? null;
                $matrix[$fieldKey]['values'][] = [
                    'definition_id' => $definition->id,
                    'raw' => $field['raw'] ?? null,
                    'normalized' => $field['normalized'] ?? null,
                    'other_text' => $field['other_text'] ?? null,
                    'other_text_normalized' => $field['other_text_normalized'] ?? null,
                    'user' => $definition->user?->only(['id', 'name']),
                    'submitted_at' => optional($definition->submitted_at)->toIso8601String(),
                ];
                $matrix[$fieldKey]['discrepancy'] = optional($discrepanciesByField->get($fieldKey))->only([
                    'severity',
                    'description',
                    'status',
                ]);
            }

            foreach ($definition->metaFields() as $fieldKey => $field) {
                $metaMatrix[$fieldKey]['label'] = $field['label'] ?? Str::headline($fieldKey);
                $metaMatrix[$fieldKey]['type'] = $field['type'] ?? 'text';
                $metaMatrix[$fieldKey]['options'] = $optionLabelMap[$fieldKey] ?? null;
                $metaMatrix[$fieldKey]['values'][] = [
                    'definition_id' => $definition->id,
                    'raw' => $field['raw'] ?? null,
                    'normalized' => $field['normalized'] ?? null,
                    'other_text' => $field['other_text'] ?? null,
                    'other_text_normalized' => $field['other_text_normalized'] ?? null,
                    'user' => $definition->user?->only(['id', 'name']),
                    'submitted_at' => optional($definition->submitted_at)->toIso8601String(),
                ];
            }
        }

        $finalDefinition = $metric->finalDefinition;
        if (! $finalDefinition) {
            $finalDefinition = FinalDefinition::make(['metric_id' => $metric->id, 'definition_json' => []]);
            $finalDefinition->setRelation('metric', $metric);
        }

        $ownerOptions = $metric->participants
            ->map(fn ($participant) => [
                'id' => (string) $participant->getKey(),
                'label' => $participant->user?->name ?? 'Pending invite',
                'responsibility' => Str::headline($participant->responsibility ?? ''),
                'is_default' => ($participant->responsibility ?? '') === 'approver',
                'user' => $participant->user?->only(['id', 'name', 'email']),
                'disabled' => $participant->user === null,
            ])
            ->values();

        $fieldRolePayload = FieldRoleSchema::forMetric($metric);

        return Inertia::render('Workshops/MetricWorkshop', [
            'organization' => $organization->only(['id', 'name']),
            'engagement' => $engagement ? [
                'id' => (string) $engagement->id,
                'title' => $engagement->title,
                'status' => $engagement->status instanceof EngagementStatus
                    ? $engagement->status->value
                    : (string) $engagement->status,
            ] : null,
            'metric' => [
                'id' => $metric->id,
                'name' => $metric->name,
                'status' => $metric->status instanceof MetricStatus ? $metric->status->value : $metric->status,
                'engagement_id' => (string) $metric->engagement_id,
                'last_submitted_at' => optional($metric->last_submitted_at)->toIso8601String(),
                'matrix' => collect($matrix)->map(fn ($row, $fieldKey) => [
                    'field' => $fieldKey,
                    'label' => $row['label'],
                    'type' => $row['type'],
                    'values' => $row['values'] ?? [],
                    'options' => $row['options'] ?? null,
                    'discrepancy' => $row['discrepancy'] ?? null,
                ])->values(),
                'context_fields' => collect($metaMatrix)->map(fn ($row, $fieldKey) => [
                    'field' => $fieldKey,
                    'label' => $row['label'],
                    'type' => $row['type'],
                    'values' => $row['values'] ?? [],
                    'options' => $row['options'] ?? null,
                ])->values(),
                'definitions' => $metric->definitions->map(fn ($definition) => [
                    'id' => $definition->id,
                    'submitted_at' => optional($definition->submitted_at)->toIso8601String(),
                    'user' => $definition->user?->only(['id', 'name']),
                ]),
                'field_roles' => $fieldRolePayload['field_roles'],
                'unclassified' => $fieldRolePayload['unclassified'],
            ],
            'finalDefinition' => [
                'id' => $finalDefinition->id,
                'status' => $finalDefinition->status,
                'definition_json' => $finalDefinition->definition_json ?? [],
                'notes' => $finalDefinition->notes,
                'owner_participant_id' => $finalDefinition->owner_participant_id
                    ? (string) $finalDefinition->owner_participant_id
                    : null,
                'owner_participant' => $finalDefinition->ownerParticipant ? [
                    'id' => (string) $finalDefinition->ownerParticipant->getKey(),
                    'responsibility' => $finalDefinition->ownerParticipant->responsibility,
                    'user' => $finalDefinition->ownerParticipant->user?->only(['id', 'name', 'email']),
                ] : null,
                'signed_off_at' => optional($finalDefinition->signed_off_at)->toIso8601String(),
                'signed_off_by' => $finalDefinition->signedOffBy?->only(['id', 'name']),
            ],
            'ownerOptions' => $ownerOptions,
            'can' => [
                'update_final_definition' => Gate::allows('update', $finalDefinition),
                'sign_off' => Gate::allows('signOff', $metric),
            ],
        ]);
    }
}
