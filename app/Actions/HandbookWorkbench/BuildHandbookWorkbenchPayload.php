<?php

namespace App\Actions\HandbookWorkbench;

use App\Enums\EngagementStatus;
use App\Enums\MetricStatus;
use App\Models\Discrepancy;
use App\Models\Engagement;
use App\Models\Membership;
use App\Models\Metric;
use App\Models\Organization;
use App\Models\OrgFinalDefinition;
use App\Support\Metrics\ExecutiveDefinitionSections;
use App\Support\Metrics\FieldOptionLabelResolver;
use App\Support\Metrics\FieldRoleSchema;

class BuildHandbookWorkbenchPayload
{
    /**
     * @return array<string, mixed>
     */
    public function handle(Organization $organization, Engagement $engagement): array
    {
        $metrics = Metric::query()
            ->where('organization_id', $organization->id)
            ->where('engagement_id', $engagement->id)
            ->with([
                'orgMetric',
                'template:id,name,key,form_schema_json',
                'workshops',
                'discrepancies',
                'definitions.user',
            ])
            ->orderBy('name')
            ->get();

        $orgMetricIds = $metrics->pluck('org_metric_id')->filter()->unique()->values()->all();

        $latestDefinitions = [];
        if (! empty($orgMetricIds)) {
            $latestDefinitions = OrgFinalDefinition::query()
                ->whereIn('org_metric_id', $orgMetricIds)
                ->whereNotNull('signed_off_at')
                ->with(['signedOffBy', 'ownerParticipant.user', 'metricTemplate:id,form_schema_json'])
                ->orderByDesc('signed_off_at')
                ->orderByDesc('updated_at')
                ->get()
                ->groupBy('org_metric_id')
                ->map(fn ($group) => $group->first());
        }

        $mappedMetrics = $metrics->map(function (Metric $metric) use ($latestDefinitions, $organization, $engagement) {
            $hasDefinition = isset($latestDefinitions[$metric->org_metric_id]);
            $isSignedOff = $metric->status === MetricStatus::FINALIZED && $hasDefinition;

            $signals = null;
            $sourceContext = null;
            if ($isSignedOff) {
                $definition = $latestDefinitions[$metric->org_metric_id];
                $signals = [
                    'org_final_definition_id' => (string) $definition->id,
                    'version' => (int) $definition->version,
                    'signed_off_at' => $definition->signed_off_at ? $definition->signed_off_at->toIso8601String() : null,
                    'signed_off_by' => $definition->signedOffBy ? [
                        'id' => (string) $definition->signedOffBy->id,
                        'name' => $definition->signedOffBy->name,
                    ] : null,
                    'executive_takeaway' => $definition->executive_takeaway,
                    'alignment_summary' => $definition->alignment_summary,
                    'operational_status' => $definition->operational_status,
                    'operational_status_notes' => $definition->operational_status_notes,
                    'implementation_gaps' => is_array($definition->implementation_gaps) ? [
                        'current_state' => $definition->implementation_gaps['current_state'] ?? null,
                        'gap' => $definition->implementation_gaps['gap'] ?? null,
                        'owner' => $definition->implementation_gaps['owner'] ?? null,
                        'suggested_next_step' => $definition->implementation_gaps['suggested_next_step'] ?? null,
                    ] : null,
                    'validation_steps' => $definition->validation_steps,
                    'common_mismatch_causes' => $definition->common_mismatch_causes,
                ];
                $sourceContext = $this->buildSourceContext($definition, $metric, $organization, $engagement);
            }

            return [
                'id' => (string) $metric->id,
                'org_metric_id' => $metric->org_metric_id ? (string) $metric->org_metric_id : null,
                'name' => $metric->name,
                'status' => $metric->status instanceof MetricStatus ? $metric->status->value : (string) $metric->status,
                'is_signed_off' => $isSignedOff,
                'template' => $metric->template ? [
                    'id' => (string) $metric->template->id,
                    'key' => $metric->template->key,
                    'name' => $metric->template->name,
                ] : null,
                'signals' => $signals,
                'source_context' => $sourceContext,
                'readiness_checklist' => $this->buildReadinessChecklist($isSignedOff, $isSignedOff ? $latestDefinitions[$metric->org_metric_id] : null),
            ];
        })->values()->all();

        return [
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
            'metrics' => $mappedMetrics,
        ];
    }

    /**
     * Build read-only source context from the signed definition for authoring support.
     *
     * @return array{signed_definition: array{version: int, signed_off_at: string|null, signed_off_by: array{id: string, name: string}|null, notes: string|null, owner: array{label: string, value: mixed}|null, sections: array<int, array{key: string, title: string, items: array<int, array{key: string, label: string, value: mixed, companions: array<int, array{key: string, label: string, value: mixed}>}>}>}, workshop_alignment: array{has_evidence: bool, workshop: array{id: string, scheduled_at: string|null, notes: string|null, decisions: array{consensus: string|null, follow_up: array<string>, field_decisions: array<string, mixed>}, workshop_url: string}|null, discrepancies: array<int, array{field_key: string, severity: string, status: string, description: string}>}, matrix_leftovers: array<int, array{field_key: string, label: string, stakeholder_positions: array<int, array{stakeholder: string, raw_value: mixed, normalized_value: mixed}>, final_value: mixed, status: string, reason: string|null, used_for: array<string>}>}
     */
    private function buildSourceContext(
        OrgFinalDefinition $definition,
        Metric $metric,
        Organization $organization,
        Engagement $engagement
    ): array {
        $definitionJson = is_array($definition->definition_json) ? $definition->definition_json : [];

        $fieldRoles = $this->resolveFieldRoles($definition, $metric);

        $owner = $this->resolveOwner($definition, $definitionJson);

        $schema = $metric->template?->form_schema_json;
        if (! is_array($schema)) {
            $schema = $definition->metricTemplate?->form_schema_json;
        }
        $optionLabelMap = is_array($schema) ? FieldOptionLabelResolver::fromSchema($schema) : [];

        $sections = ExecutiveDefinitionSections::build($definitionJson, $fieldRoles, $owner, $optionLabelMap);

        $discrepancies = $metric->discrepancies
            ->filter(fn ($d) => $this->isResolvedDiscrepancy($d))
            ->map(fn ($d) => [
                'field_key' => (string) $d->field_key,
                'severity' => (string) $d->severity,
                'status' => (string) $d->status,
                'description' => (string) $d->description,
            ])
            ->values()
            ->all();

        $workshopAlignment = $this->buildWorkshopAlignmentContext($metric, $organization, $engagement, $discrepancies);

        return [
            'signed_definition' => [
                'version' => (int) $definition->version,
                'signed_off_at' => $definition->signed_off_at
                    ? $definition->signed_off_at->toIso8601String()
                    : null,
                'signed_off_by' => $definition->signedOffBy ? [
                    'id' => (string) $definition->signedOffBy->id,
                    'name' => $definition->signedOffBy->name,
                ] : null,
                'notes' => $definition->notes,
                'owner' => $owner,
                'sections' => $sections,
            ],
            'workshop_alignment' => $workshopAlignment,
            'matrix_leftovers' => $this->buildMatrixLeftovers($definition, $metric, $organization, $definitionJson),
        ];
    }

    /**
     * @param  array<string, mixed>  $definitionJson
     * @return array<int, array{field_key: string, label: string, stakeholder_positions: array<int, array{stakeholder: string, raw_value: mixed, normalized_value: mixed}>, final_value: mixed, status: string, reason: string|null, used_for: array<string>}>
     */
    private function buildMatrixLeftovers(
        OrgFinalDefinition $definition,
        Metric $metric,
        Organization $organization,
        array $definitionJson
    ): array {
        $leftovers = [];

        if ($metric->template && $metric->template->key === 'cac_cpl') {
            $leftoverFields = [
                'costs_included' => [
                    'label' => 'CAC/CPL cost components included',
                    'default_status' => 'preserved_operating_view',
                    'used_for' => ['implementation_gaps', 'common_mismatch_causes'],
                ],
                'lead_definition' => [
                    'label' => 'CPL lead definition',
                    'default_status' => 'rejected',
                    'used_for' => ['common_mismatch_causes', 'validation'],
                ],
                'customer_definition' => [
                    'label' => 'CAC customer acquisition point',
                    'default_status' => 'rejected',
                    'used_for' => ['validation'],
                ],
                'cost_timing' => [
                    'label' => 'Cost-to-outcome timing alignment method',
                    'default_status' => 'rejected',
                    'used_for' => ['common_mismatch_causes'],
                ],
                'deduplication_method' => [
                    'label' => 'Cross-campaign deduplication method',
                    'default_status' => 'rejected',
                    'used_for' => ['validation'],
                ],
                'overhead_handling' => [
                    'label' => 'Overhead and indirect cost treatment',
                    'default_status' => 'preserved_operating_view',
                    'used_for' => ['implementation_gaps', 'common_mismatch_causes'],
                ],
            ];

            $memberships = Membership::where('organization_id', $organization->id)
                ->get()
                ->keyBy('user_id');

            $latestWorkshop = $metric->workshops
                ->sortByDesc(fn ($w) => $w->scheduled_at ?? $w->created_at)
                ->first();
            $decisions = $latestWorkshop ? $this->normalizeWorkshopDecisions($latestWorkshop->decision_snapshot) : null;

            foreach ($leftoverFields as $fieldKey => $config) {
                $finalValue = $definitionJson[$fieldKey]['value'] ?? null;

                $stakeholderPositions = [];
                $hasDifference = false;

                foreach ($metric->definitions as $subDef) {
                    $teamDept = $subDef->normalizedField('team_department');
                    $affiliation = $teamDept['raw'] ?? $teamDept['normalized'] ?? null;
                    if (! $affiliation) {
                        $membership = $memberships[$subDef->user_id] ?? null;
                        $affiliation = $membership?->affiliation;
                    }
                    if (! $affiliation) {
                        $affiliation = 'Unknown';
                    }

                    $fieldVal = $subDef->normalizedField($fieldKey);
                    if ($fieldVal !== null) {
                        $rawVal = $fieldVal['raw'] ?? null;
                        $normVal = $fieldVal['normalized'] ?? null;

                        if ($rawVal !== null || $normVal !== null) {
                            $stakeholderPositions[] = [
                                'stakeholder' => (string) $affiliation,
                                'raw_value' => $rawVal,
                                'normalized_value' => $normVal,
                            ];

                            if (! $this->valuesAreEqual($rawVal, $finalValue) && ! $this->valuesAreEqual($normVal, $finalValue)) {
                                $hasDifference = true;
                            }
                        }
                    }
                }

                if ($hasDifference && ! empty($stakeholderPositions)) {
                    $fieldDecision = $decisions['field_decisions'][$fieldKey] ?? null;
                    $status = $fieldDecision['status'] ?? $config['default_status'];
                    $reason = $fieldDecision['rationale'] ?? null;

                    $leftovers[] = [
                        'field_key' => $fieldKey,
                        'label' => $config['label'],
                        'stakeholder_positions' => $stakeholderPositions,
                        'final_value' => $finalValue,
                        'status' => $status,
                        'reason' => $reason,
                        'used_for' => $config['used_for'],
                    ];
                }
            }
        }

        return $leftovers;
    }

    private function valuesAreEqual(mixed $a, mixed $b): bool
    {
        if (is_array($a) && is_array($b)) {
            sort($a);
            sort($b);

            return $a == $b;
        }

        if (is_array($a) || is_array($b)) {
            return false;
        }

        return $a == $b;
    }

    private function isResolvedDiscrepancy(Discrepancy $discrepancy): bool
    {
        if ($discrepancy->resolved_at !== null) {
            return true;
        }

        return strcasecmp((string) $discrepancy->status, 'Resolved') === 0;
    }

    /**
     * Build the normalized workshop alignment context.
     *
     * @param  array<int, array{field_key: string, severity: string, status: string, description: string}>  $discrepancies
     * @return array{has_evidence: bool, workshop: array{id: string, scheduled_at: string|null, notes: string|null, decisions: array{consensus: string|null, follow_up: array<string>}, workshop_url: string}|null, discrepancies: array<int, array{field_key: string, severity: string, status: string, description: string}>}
     */
    private function buildWorkshopAlignmentContext(
        Metric $metric,
        Organization $organization,
        Engagement $engagement,
        array $discrepancies
    ): array {
        $latestWorkshop = $metric->workshops
            ->sortByDesc(fn ($w) => $w->scheduled_at ?? $w->created_at)
            ->first();

        $workshopPayload = null;
        $hasWorkshopEvidence = false;

        if ($latestWorkshop) {
            $decisions = $this->normalizeWorkshopDecisions($latestWorkshop->decision_snapshot);
            $hasNotes = ! empty(trim($latestWorkshop->notes ?? ''));
            $hasDecisions = ! empty($decisions['consensus'])
                || ! empty($decisions['follow_up'])
                || ! empty($decisions['field_decisions']);

            if ($hasNotes || $hasDecisions) {
                $hasWorkshopEvidence = true;
                $workshopPayload = [
                    'id' => (string) $latestWorkshop->id,
                    'scheduled_at' => $latestWorkshop->scheduled_at
                        ? $latestWorkshop->scheduled_at->toIso8601String()
                        : null,
                    'notes' => $latestWorkshop->notes,
                    'decisions' => $decisions,
                    'workshop_url' => route('org.engagements.workshop', [$organization, $engagement, 'metric' => $metric->id]),
                ];
            }
        }

        return [
            'has_evidence' => $hasWorkshopEvidence || count($discrepancies) > 0,
            'workshop' => $workshopPayload,
            'discrepancies' => $discrepancies,
        ];
    }

    /**
     * Normalize decision snapshot array to standardized consensus/follow_up keys.
     *
     * @param  array<string, mixed>|null  $decisionSnapshot
     * @return array{consensus: string|null, follow_up: array<string>, field_decisions: array<string, mixed>}
     */
    private function normalizeWorkshopDecisions(?array $decisionSnapshot): array
    {
        if (empty($decisionSnapshot)) {
            return [
                'consensus' => null,
                'follow_up' => [],
                'field_decisions' => [],
            ];
        }

        $consensus = $this->normalizeDecisionText($decisionSnapshot['consensus'] ?? null);

        $followUp = $decisionSnapshot['follow_up'] ?? $decisionSnapshot['follow_ups'] ?? [];
        if (! is_array($followUp)) {
            $followUp = [$followUp];
        }

        $followUp = array_values(array_filter(
            array_map(fn (mixed $item) => $this->normalizeDecisionText($item), $followUp),
            fn (?string $item) => $item !== null
        ));

        $fieldDecisions = $decisionSnapshot['field_decisions'] ?? [];
        if (! is_array($fieldDecisions)) {
            $fieldDecisions = [];
        }

        return [
            'consensus' => $consensus,
            'follow_up' => $followUp,
            'field_decisions' => $fieldDecisions,
        ];
    }

    private function normalizeDecisionText(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    /**
     * Resolve field roles from the metric template schema for section ordering.
     *
     * @return array<int, array<string, mixed>>
     */
    private function resolveFieldRoles(OrgFinalDefinition $definition, Metric $metric): array
    {
        $schema = $metric->template?->form_schema_json;

        if (! is_array($schema)) {
            $schema = $definition->metricTemplate?->form_schema_json;
        }

        if (! is_array($schema)) {
            return [];
        }

        $payload = FieldRoleSchema::forTemplateSchema($schema);

        return is_array($payload['field_roles'] ?? null) ? $payload['field_roles'] : [];
    }

    /**
     * Resolve the metric owner from the definition participant or definition_json fields.
     *
     * @param  array<string, mixed>  $definitionJson
     * @return array{label: string, value: mixed}|null
     */
    private function resolveOwner(OrgFinalDefinition $definition, array $definitionJson): ?array
    {
        if ($definition->owner_participant_id && $definition->ownerParticipant) {
            return [
                'label' => 'Metric Owner',
                'value' => $definition->ownerParticipant->user?->name ?? 'Pending invite',
            ];
        }

        // Fall back to owner-like fields in definition_json
        foreach ($definitionJson as $key => $field) {
            if (! is_array($field)) {
                continue;
            }

            $normalizedKey = strtolower((string) $key);
            $label = $field['label'] ?? null;
            $normalizedLabel = is_string($label) ? strtolower($label) : '';

            if (str_contains($normalizedKey, 'owner') || str_contains($normalizedLabel, 'owner')) {
                return [
                    'label' => is_string($label) && trim($label) !== '' ? $label : 'Metric Owner',
                    'value' => $field['value'] ?? null,
                ];
            }
        }

        return null;
    }

    /**
     * @return array<string, array{label: string, status: string}>
     */
    private function buildReadinessChecklist(bool $isSignedOff, ?OrgFinalDefinition $definition): array
    {
        $signedDefinitionStatus = $isSignedOff ? 'ready' : 'missing';

        $approvalVersionStatus = 'missing';
        if ($isSignedOff && $definition && $definition->version >= 1 && $definition->signed_off_at && $definition->signed_off_by) {
            $approvalVersionStatus = 'ready';
        }

        $executiveTakeawayStatus = 'missing';
        if ($isSignedOff && $definition && ! empty(trim($definition->executive_takeaway ?? ''))) {
            $executiveTakeawayStatus = 'ready';
        }

        $implementationReviewStatus = 'missing';
        if ($isSignedOff && $definition) {
            if ($definition->operational_status === 'operationally_ready') {
                $implementationReviewStatus = 'ready';
            } elseif ($definition->operational_status === 'needs_follow_up') {
                $gaps = $definition->implementation_gaps;
                if (is_array($gaps)) {
                    $hasCurrentState = ! empty(trim($gaps['current_state'] ?? ''));
                    $hasGap = ! empty(trim($gaps['gap'] ?? ''));
                    $hasOwner = ! empty(trim($gaps['owner'] ?? ''));
                    $hasSuggestedNextStep = ! empty(trim($gaps['suggested_next_step'] ?? $gaps['next_step'] ?? ''));

                    if ($hasCurrentState && $hasGap && $hasOwner && $hasSuggestedNextStep) {
                        $implementationReviewStatus = 'ready';
                    }
                }
            }
        }

        $validationGuidanceStatus = 'missing';
        if ($isSignedOff && $definition && ! empty(trim($definition->validation_steps ?? '')) && ! empty(trim($definition->common_mismatch_causes ?? ''))) {
            $validationGuidanceStatus = 'ready';
        }

        $executiveAlignmentStatus = 'missing';
        if ($isSignedOff && $definition && ! empty(trim($definition->alignment_summary ?? ''))) {
            $executiveAlignmentStatus = 'ready';
        }

        return [
            'signed_definition' => [
                'label' => 'Signed Definition',
                'status' => $signedDefinitionStatus,
            ],
            'approval_version' => [
                'label' => 'Approval & Version',
                'status' => $approvalVersionStatus,
            ],
            'executive_takeaway' => [
                'label' => 'Executive Takeaway',
                'status' => $executiveTakeawayStatus,
            ],
            'executive_alignment' => [
                'label' => 'Executive Alignment',
                'status' => $executiveAlignmentStatus,
            ],
            'implementation_review' => [
                'label' => 'Implementation Follow-up',
                'status' => $implementationReviewStatus,
            ],
            'validation_guidance' => [
                'label' => 'Validation Guidance',
                'status' => $validationGuidanceStatus,
            ],
        ];
    }
}
