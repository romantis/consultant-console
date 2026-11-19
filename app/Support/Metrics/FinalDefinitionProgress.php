<?php

namespace App\Support\Metrics;

use App\Models\FinalDefinition;
use App\Models\Metric;

class FinalDefinitionProgress
{
    /**
     * @param  array{required_fields: array<int, array{key: string, label: string}>, missing_keys: array<int, string>, owner_eligible: bool}  $requirements
     * @return array{state: string, missing_count?: int}
     */
    public static function fromRequirements(array $requirements, bool $signedOff): array
    {
        if ($signedOff) {
            return ['state' => 'signed_off'];
        }

        $missingCount = count($requirements['missing_keys'] ?? []);

        if (! ($requirements['owner_eligible'] ?? false)) {
            $missingCount += 1;
        }

        if ($missingCount === 0) {
            return [
                'state' => 'ready',
                'missing_count' => 0,
            ];
        }

        return [
            'state' => 'missing',
            'missing_count' => $missingCount,
        ];
    }

    /**
     * @return array{state: string, missing_count?: int}
     */
    public static function forMetric(Metric $metric, ?FinalDefinition $finalDefinition = null): array
    {
        $finalDefinition = $finalDefinition ?? $metric->finalDefinition;
        $requirements = FinalDefinitionRequirements::forMetric($metric, $finalDefinition);

        return self::fromRequirements($requirements, (bool) $finalDefinition?->signed_off_at);
    }
}
