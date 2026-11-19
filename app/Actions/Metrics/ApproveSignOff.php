<?php

namespace App\Actions\Metrics;

use App\Actions\Audit\LogMetricEvent;
use App\Actions\OrgMetrics\ApproveOrgMetricSignOff;
use App\Actions\OrgMetrics\RequestOrgMetricSignOffChanges;
use App\Models\FinalDefinition;
use App\Models\Metric;
use App\Models\User;
use App\Support\Metrics\FinalDefinitionRequirements;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ApproveSignOff
{
    public function __construct(
        private LogMetricEvent $logMetricEvent,
        private ApproveOrgMetricSignOff $approveOrgMetricSignOff,
        private RequestOrgMetricSignOffChanges $requestOrgMetricSignOffChanges
    ) {}

    /**
     * @param  array{decision:string|null, comment?:string|null}  $payload
     * @return array{metric_id:string, final_definition_id:string, status:string, signed_off_at:?string, signed_off_by:?array, decision:string, comment:?string}
     *
     * @throws ValidationException
     */
    public function handle(Metric $metric, array $payload, ?User $actor = null): array
    {
        $finalDefinition = $metric->finalDefinition;

        if (! $finalDefinition) {
            throw ValidationException::withMessages([
                'decision' => __('Finalize the draft definition before recording a decision.'),
            ]);
        }

        $decision = $this->normalizeDecision($payload['decision'] ?? null);
        $comment = $this->sanitizeComment($payload['comment'] ?? null);

        return $decision === 'approve'
            ? $this->approve($metric, $finalDefinition, $actor, $comment)
            : $this->requestChanges($metric, $finalDefinition, $actor, $comment);
    }

    private function approve(Metric $metric, FinalDefinition $finalDefinition, ?User $actor, ?string $comment): array
    {
        $this->assertDefinitionIsComplete($metric, $finalDefinition);

        if ($finalDefinition->signed_off_at) {
            throw ValidationException::withMessages([
                'decision' => __('This metric is already signed off.'),
            ]);
        }

        $this->approveOrgMetricSignOff->handle($metric, $finalDefinition, $comment, $actor);

        $finalDefinition->refresh();

        $this->logMetricEvent->handle(
            $metric,
            'metric.final-definition.signed-off',
            array_filter([
                'final_definition_id' => (string) $finalDefinition->getKey(),
                'comment' => $comment,
                'decision' => 'approve',
                'signed_off_at' => optional($finalDefinition->signed_off_at)->toIso8601String(),
            ], fn ($value) => $value !== null),
            $actor,
            $finalDefinition
        );

        return [
            'metric_id' => (string) $metric->getKey(),
            'final_definition_id' => (string) $finalDefinition->getKey(),
            'status' => (string) $finalDefinition->status,
            'signed_off_at' => optional($finalDefinition->signed_off_at)->toIso8601String(),
            'signed_off_by' => $finalDefinition->signedOffBy?->only(['id', 'name']),
            'decision' => 'approve',
            'comment' => $comment,
        ];
    }

    private function requestChanges(Metric $metric, FinalDefinition $finalDefinition, ?User $actor, ?string $comment): array
    {
        $this->requestOrgMetricSignOffChanges->handle($metric, $finalDefinition, $comment, $actor);

        $finalDefinition->refresh();

        $this->logMetricEvent->handle(
            $metric,
            'metric.sign-off.changes-requested',
            array_filter([
                'final_definition_id' => (string) $finalDefinition->getKey(),
                'comment' => $comment,
                'decision' => 'request_changes',
            ], fn ($value) => $value !== null),
            $actor,
            $finalDefinition
        );

        return [
            'metric_id' => (string) $metric->getKey(),
            'final_definition_id' => (string) $finalDefinition->getKey(),
            'status' => (string) $finalDefinition->status,
            'signed_off_at' => null,
            'signed_off_by' => null,
            'decision' => 'request_changes',
            'comment' => $comment,
        ];
    }

    private function normalizeDecision(?string $decision): string
    {
        $normalized = Str::of((string) $decision)->lower()->value();

        if (! in_array($normalized, ['approve', 'request_changes'], true)) {
            throw ValidationException::withMessages([
                'decision' => __('Select a valid decision (approve or request_changes).'),
            ]);
        }

        return $normalized;
    }

    private function assertDefinitionIsComplete(Metric $metric, FinalDefinition $finalDefinition): void
    {
        $requirements = FinalDefinitionRequirements::forMetric($metric, $finalDefinition);
        $message = self::signOffReadinessMessage($requirements);

        if ($message !== null) {
            throw ValidationException::withMessages([
                'final_definition_id' => __($message),
            ]);
        }
    }

    /**
     * @param  array{
     *   required_fields?: array<int, array{key?: string, label?: string}>,
     *   missing_keys?: array<int, string>,
     *   owner_eligible?: bool
     * }  $requirements
     */
    public static function signOffReadinessMessage(array $requirements): ?string
    {
        $missingLabels = self::missingRequirementLabels($requirements);
        $ownerEligible = (bool) ($requirements['owner_eligible'] ?? false);

        if ($missingLabels === [] && $ownerEligible) {
            return null;
        }

        if ($missingLabels !== [] && ! $ownerEligible) {
            return sprintf(
                'Complete required fields (%s) and assign a metric owner before approving sign-off.',
                implode(', ', $missingLabels)
            );
        }

        if ($missingLabels !== []) {
            return sprintf(
                'Complete required fields (%s) before approving sign-off.',
                implode(', ', $missingLabels)
            );
        }

        return 'Assign a metric owner before approving sign-off.';
    }

    /**
     * @param  array{
     *   required_fields?: array<int, array{key?: string, label?: string}>,
     *   missing_keys?: array<int, string>
     * }  $requirements
     * @return list<string>
     */
    private static function missingRequirementLabels(array $requirements): array
    {
        $labelsByKey = [];

        foreach ($requirements['required_fields'] ?? [] as $field) {
            $key = $field['key'] ?? null;
            $label = $field['label'] ?? null;

            if (! is_string($key) || $key === '') {
                continue;
            }

            $labelsByKey[$key] = is_string($label) && $label !== ''
                ? $label
                : Str::headline($key);
        }

        $missingLabels = [];

        foreach ($requirements['missing_keys'] ?? [] as $key) {
            if (! is_string($key) || $key === '') {
                continue;
            }

            $missingLabels[] = $labelsByKey[$key] ?? Str::headline($key);
        }

        return array_values(array_unique($missingLabels));
    }

    private function sanitizeComment(?string $comment): ?string
    {
        $comment = $comment !== null ? trim($comment) : null;

        if ($comment === null || $comment === '') {
            return null;
        }

        return Str::limit($comment, 2000);
    }
}
