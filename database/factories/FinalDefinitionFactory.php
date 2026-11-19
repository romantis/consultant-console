<?php

namespace Database\Factories;

use App\Models\FinalDefinition;
use App\Models\Metric;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FinalDefinition>
 */
class FinalDefinitionFactory extends Factory
{
    protected $model = FinalDefinition::class;

    public function definition(): array
    {
        return [
            'metric_id' => Metric::factory(),
            'user_id' => User::factory(),
            'version' => 1,
            'status' => 'Draft',
            'definition_json' => [
                'value' => 'Normalized metric definition',
            ],
            'notes' => $this->faker->sentence(10),
            'operational_status' => null,
            'operational_status_notes' => null,
            'alignment_summary' => null,
            'implementation_gaps' => null,
            'validation_steps' => null,
            'common_mismatch_causes' => null,
            'executive_takeaway' => null,
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
