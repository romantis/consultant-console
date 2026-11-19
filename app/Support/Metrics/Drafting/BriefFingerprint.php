<?php

namespace App\Support\Metrics\Drafting;

use JsonException;

final readonly class BriefFingerprint
{
    /**
     * Calculate a deterministic sha256 fingerprint hash of the given metric alignment brief.
     *
     * @param  array<string, mixed>  $brief
     *
     * @throws JsonException
     */
    public static function calculate(array $brief): string
    {
        return hash('sha256', json_encode($brief, JSON_THROW_ON_ERROR));
    }

    /**
     * Fingerprint covering the alignment brief and the workshop readiness gaps.
     * A facilitation plan persists only the readiness gaps, so it must be flagged
     * for review when those gaps change — but the rest of the readiness snapshot
     * (e.g. a newly represented but already-required department) must not move the
     * fingerprint and flag an otherwise-current plan.
     *
     * @param  array<string, mixed>  $brief
     * @param  array<string, mixed>  $workshopReadiness
     *
     * @throws JsonException
     */
    public static function forFacilitationPlan(array $brief, array $workshopReadiness): string
    {
        return self::calculate([
            'brief' => $brief,
            'readiness_gaps' => $workshopReadiness['gaps'] ?? [],
        ]);
    }
}
