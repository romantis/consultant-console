<?php

namespace App\Actions\OrgMetrics;

use App\Actions\Audit\LogOrgMetricEvent;
use App\Models\OrgFinalDefinition;
use App\Models\OrgMetric;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class UpdateOrgMetricSignals
{
    public function __construct(
        private readonly LogOrgMetricEvent $logOrgMetricEvent
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(OrgMetric $orgMetric, array $payload, ?User $actor = null): OrgFinalDefinition
    {
        $definition = $this->resolveLatestOfficialDefinition($orgMetric);

        $definition->fill($payload);

        $updatedFields = array_values(array_filter(
            array_keys($payload),
            static fn (string $field) => $definition->isDirty($field)
        ));

        $definition->save();

        $this->logOrgMetricEvent->handle(
            $orgMetric,
            'org-metric.signals.updated',
            [
                'org_final_definition_id' => (string) $definition->getKey(),
                'updated_fields' => $updatedFields,
            ],
            $actor,
            $definition
        );

        return tap($definition)->refresh();
    }

    private function resolveLatestOfficialDefinition(OrgMetric $orgMetric): OrgFinalDefinition
    {
        $definition = OrgFinalDefinition::query()
            ->where('org_metric_id', $orgMetric->getKey())
            ->whereNotNull('signed_off_at')
            ->orderByDesc('signed_off_at')
            ->orderByDesc('updated_at')
            ->first();

        if ($definition instanceof OrgFinalDefinition) {
            return $definition;
        }

        throw ValidationException::withMessages([
            'org_metric' => __('Sign off this metric before updating official consultant signals.'),
        ]);
    }
}
