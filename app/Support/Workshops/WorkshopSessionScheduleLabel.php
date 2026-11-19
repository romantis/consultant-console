<?php

namespace App\Support\Workshops;

use Carbon\CarbonInterface;

final class WorkshopSessionScheduleLabel
{
    public static function format(
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
        string $timezone,
    ): string {
        $start = $startsAt->copy()->timezone($timezone);
        $end = $endsAt->copy()->timezone($timezone);

        if ($start->isSameDay($end)) {
            return sprintf(
                '%s %s to %s (%s)',
                $start->format('M j, Y'),
                $start->format('g:i A'),
                $end->format('g:i A'),
                $timezone,
            );
        }

        return sprintf(
            '%s to %s (%s)',
            $start->format('M j, Y g:i A'),
            $end->format('M j, Y g:i A'),
            $timezone,
        );
    }
}
