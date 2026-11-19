<?php

namespace App\Policies;

use App\Enums\MembershipRole;
use App\Models\MetricParticipant;
use App\Models\User;
use App\Support\ImpersonationGuard;

class MetricParticipantPolicy
{
    public function update(User $user, MetricParticipant $metricParticipant): bool
    {
        $metricParticipant->loadMissing('metric');
        $metric = $metricParticipant->metric;

        if (! $metric) {
            return false;
        }

        return ImpersonationGuard::resolve($user, $metric->organization_id)
            ->allowsAtLeast(MembershipRole::ADMIN);
    }
}
