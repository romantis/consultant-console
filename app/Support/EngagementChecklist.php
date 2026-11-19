<?php

namespace App\Support;

use App\Models\Engagement;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class EngagementChecklist
{
    public const MODULE_METRICS = 'metrics';

    public const MODULE_FORMS = 'forms';

    public const MODULE_DISCREPANCIES = 'discrepancies';

    public const MODULE_WORKSHOPS = 'workshops';

    public static function defaults(): array
    {
        return [
            'metrics_selected' => false,
            'stakeholders_invited' => false,
            'repository_connected' => false,
            'forms_bootstrapped' => false,
            'workshop_ready' => false,
        ];
    }

    public static function merge(array $checklist, array $updates): array
    {
        return array_replace(self::defaults(), $checklist, $updates);
    }

    public static function ensureReadyFor(string $module, Engagement $engagement): void
    {
        $checklist = array_replace(self::defaults(), $engagement->onboarding_checklist_json ?? []);

        $requirements = self::requirementsFor($module);

        if (empty($requirements)) {
            return;
        }

        foreach ($requirements as $flag) {
            if (! Arr::get($checklist, $flag)) {
                throw ValidationException::withMessages([
                    'engagement' => __('Engagement onboarding incomplete (:step)', ['step' => str_replace('_', ' ', $flag)]),
                ]);
            }
        }
    }

    protected static function requirementsFor(string $module): array
    {
        return match ($module) {
            self::MODULE_METRICS => ['metrics_selected'],
            self::MODULE_FORMS => ['metrics_selected', 'stakeholders_invited'],
            self::MODULE_DISCREPANCIES => ['metrics_selected', 'forms_bootstrapped'],
            self::MODULE_WORKSHOPS => ['metrics_selected', 'workshop_ready'],
            default => [],
        };
    }
}
