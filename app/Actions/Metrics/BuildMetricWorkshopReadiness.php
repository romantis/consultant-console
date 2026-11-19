<?php

namespace App\Actions\Metrics;

use App\Models\Metric;
use App\Support\Metrics\WorkshopReadinessSnapshot;
use Illuminate\Support\Str;

final readonly class BuildMetricWorkshopReadiness
{
    public function handle(Metric $metric): WorkshopReadinessSnapshot
    {
        $metric->loadMissing([
            'template',
            'definitions' => fn ($query) => $query->where('status', 'Submitted'),
        ]);

        $requiredDepartments = collect(data_get($metric->template?->form_schema_json, 'required_departments', []))
            ->filter(fn (mixed $department): bool => is_string($department) && trim($department) !== '')
            ->map(fn (string $department): string => trim($department))
            ->unique(fn (string $department): string => $this->departmentKey($department))
            ->values();

        $requiredDepartmentLabels = $requiredDepartments
            ->mapWithKeys(fn (string $department): array => [
                $this->departmentKey($department) => $department,
            ]);

        $representedDepartments = $metric->definitions
            ->filter(fn ($definition): bool => $definition->status === 'Submitted')
            ->map(function ($definition): mixed {
                $normalizedField = $definition->normalizedField('team_department');

                return $normalizedField['raw']
                    ?? $normalizedField['normalized']
                    ?? data_get($definition->definition_json, 'team_department');
            })
            ->filter(fn (mixed $department): bool => is_string($department) && trim($department) !== '')
            ->map(fn (string $department): string => trim($department))
            ->map(function (string $department) use ($requiredDepartmentLabels): string {
                return $requiredDepartmentLabels->get(
                    $this->departmentKey($department),
                    $department,
                );
            })
            ->unique(fn (string $department): string => $this->departmentKey($department))
            ->values();

        $representedDepartmentKeys = $representedDepartments
            ->map(fn (string $department): string => $this->departmentKey($department))
            ->all();

        $missingRequiredDepartments = $requiredDepartments
            ->reject(fn (string $department): bool => in_array(
                $this->departmentKey($department),
                $representedDepartmentKeys,
                true,
            ))
            ->values()
            ->all();

        return new WorkshopReadinessSnapshot(
            templateKey: (string) ($metric->template?->key ?? 'metric'),
            requiredDepartments: $requiredDepartments->all(),
            representedDepartments: $representedDepartments->all(),
            missingRequiredDepartments: $missingRequiredDepartments,
        );
    }

    private function departmentKey(string $department): string
    {
        return Str::lower((string) Str::of($department)->squish());
    }
}
