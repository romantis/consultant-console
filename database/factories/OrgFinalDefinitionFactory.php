<?php

namespace Database\Factories;

use App\Models\Metric;
use App\Models\MetricTemplate;
use App\Models\OrgFinalDefinition;
use App\Models\OrgMetric;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrgFinalDefinition>
 */
class OrgFinalDefinitionFactory extends Factory
{
    protected $model = OrgFinalDefinition::class;

    public function definition(): array
    {
        return [
            'org_metric_id' => OrgMetric::factory(),
            'source_metric_id' => null,
            'source_metric_template_id' => null,
            'status' => 'PendingApproval',
            'version' => 1,
            'definition_json' => [],
            'notes' => null,
            'operational_status' => null,
            'operational_status_notes' => null,
            'alignment_summary' => null,
            'implementation_gaps' => null,
            'validation_steps' => null,
            'common_mismatch_causes' => null,
            'executive_takeaway' => null,
            'owner_participant_id' => null,
            'signed_off_at' => null,
            'signed_off_by' => null,
        ];
    }

    /**
     * @param  array{
     *     operational_status?: ?string,
     *     operational_status_notes?: ?string,
     *     alignment_summary?: ?string,
     *     implementation_gaps?: ?array<string, string>,
     *     validation_steps?: ?string,
     *     common_mismatch_causes?: ?string,
     *     executive_takeaway?: ?string
     * }  $overrides
     */
    public function withConsultantSignals(array $overrides = []): static
    {
        return $this->state(fn () => [
            'operational_status' => $this->signalOverride(
                $overrides,
                'operational_status',
                'needs_follow_up'
            ),
            'operational_status_notes' => $this->signalOverride(
                $overrides,
                'operational_status_notes',
                'Dashboard filters still need one client-side validation pass.'
            ),
            'alignment_summary' => $this->signalOverride(
                $overrides,
                'alignment_summary',
                'Finance, product, and operations aligned on one board-facing definition.'
            ),
            'implementation_gaps' => $this->implementationGapsOverride($overrides),
            'validation_steps' => $this->signalOverride(
                $overrides,
                'validation_steps',
                'Compare workbook totals to the approved board pack.'
            ),
            'common_mismatch_causes' => $this->signalOverride(
                $overrides,
                'common_mismatch_causes',
                'Dashboard filters and late source-system backfills can drift from the signed definition.'
            ),
            'executive_takeaway' => $this->signalOverride(
                $overrides,
                'executive_takeaway',
                'Authored executive takeaway for the metric.'
            ),
        ]);
    }

    public function configure(): static
    {
        return $this->afterMaking(function (OrgFinalDefinition $definition) {
            $updates = [];

            $metric = $definition->source_metric_id
                ? Metric::query()->find($definition->source_metric_id)
                : null;

            $template = $definition->source_metric_template_id
                ? MetricTemplate::query()->find($definition->source_metric_template_id)
                : null;

            if (! $template && $metric?->metric_template_id) {
                $template = MetricTemplate::query()->find($metric->metric_template_id);
                if ($template) {
                    $updates['source_metric_template_id'] = $template->getKey();
                }
            }

            $orgMetric = $definition->org_metric_id
                ? OrgMetric::query()->find($definition->org_metric_id)
                : null;

            if (! $orgMetric && $metric?->org_metric_id) {
                $orgMetric = OrgMetric::query()->find($metric->org_metric_id);
                if ($orgMetric) {
                    $updates['org_metric_id'] = $orgMetric->getKey();
                }
            }

            if (! $orgMetric) {
                $orgMetric = OrgMetric::factory()->create();
                $updates['org_metric_id'] = $orgMetric->getKey();
            }

            if (! $template) {
                $template = MetricTemplate::factory()->create([
                    'canonical_metric_id' => $orgMetric->canonical_metric_id,
                    'org_metric_id' => $orgMetric->getKey(),
                    'key' => $orgMetric->key,
                    'name' => $orgMetric->name,
                ]);
                $updates['source_metric_template_id'] = $template->getKey();
            }

            if (! $metric) {
                $metric = Metric::factory()->create([
                    'organization_id' => $orgMetric->organization_id,
                    'engagement_id' => null,
                    'org_metric_id' => $orgMetric->getKey(),
                    'metric_template_id' => $template->getKey(),
                    'key' => $orgMetric->key,
                    'name' => $orgMetric->name,
                ]);
                $updates['source_metric_id'] = $metric->getKey();
            }

            if ($metric && ! $definition->source_metric_id) {
                $updates['source_metric_id'] = $metric->getKey();
            }

            if ($updates !== []) {
                $definition->forceFill($updates);
            }
        });
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function signalOverride(array $overrides, string $key, mixed $default): mixed
    {
        return array_key_exists($key, $overrides) ? $overrides[$key] : $default;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, string>|null
     */
    private function implementationGapsOverride(array $overrides): ?array
    {
        $defaults = [
            'current_state' => 'Definition is documented, but the published report still depends on a manual reconciliation step.',
            'gap' => 'Dashboard logic needs one client-side update to match the signed definition.',
            'owner' => 'Revenue ops',
            'suggested_next_step' => 'Update the report before the next review cycle.',
        ];

        if (! array_key_exists('implementation_gaps', $overrides)) {
            return $defaults;
        }

        $override = $overrides['implementation_gaps'];

        if ($override === null) {
            return null;
        }

        return array_replace($defaults, $override);
    }
}
