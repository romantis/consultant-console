<?php

namespace App\Enums;

enum EngagementStatus: string
{
    case DRAFT = 'Draft';
    case AWAITING_FORMS = 'AwaitingForms';
    case IN_REVIEW = 'InReview';
    case IN_WORKSHOP = 'InWorkshop';
    case FINALIZED = 'Finalized';

    /**
     * @return list<self>
     */
    public static function activePhases(): array
    {
        return [
            self::AWAITING_FORMS,
            self::IN_REVIEW,
            self::IN_WORKSHOP,
        ];
    }

    /**
     * @return list<self>
     */
    public static function terminalPhases(): array
    {
        return [
            self::FINALIZED,
        ];
    }

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::DRAFT => [self::AWAITING_FORMS],
            self::AWAITING_FORMS => [self::IN_REVIEW],
            self::IN_REVIEW => [self::IN_WORKSHOP],
            self::IN_WORKSHOP => [self::IN_REVIEW, self::FINALIZED],
            self::FINALIZED => [],
        };
    }

    /**
     * @return list<self>
     */
    public static function transitionOptions(?self $current): array
    {
        if (! $current) {
            return self::cases();
        }

        $options = array_merge([$current], $current->allowedTransitions());
        $unique = [];

        foreach ($options as $status) {
            $unique[$status->value] = $status;
        }

        return array_values($unique);
    }

    public static function fromLegacy(string $value): self
    {
        return self::from(self::normalizeLegacyValue($value));
    }

    public static function tryFromLegacy(?string $value): ?self
    {
        if ($value === null) {
            return null;
        }

        return self::tryFrom(self::normalizeLegacyValue($value));
    }

    private static function normalizeLegacyValue(string $value): string
    {
        $trimmed = trim($value);

        // Legacy "Active" maps to AwaitingForms after status simplification.
        if (strcasecmp($trimmed, 'Active') === 0) {
            return self::AWAITING_FORMS->value;
        }

        return $trimmed;
    }
}
