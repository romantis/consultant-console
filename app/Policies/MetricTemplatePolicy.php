<?php

namespace App\Policies;

use App\Models\MetricTemplate;
use App\Models\User;
use App\Support\Staff;

class MetricTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return Staff::isStaff($user);
    }

    public function view(User $user, MetricTemplate $metricTemplate): bool
    {
        return Staff::isStaff($user);
    }

    public function create(User $user): bool
    {
        return Staff::isStaff($user);
    }

    public function update(User $user, MetricTemplate $metricTemplate): bool
    {
        return Staff::isStaff($user);
    }

    public function delete(User $user, MetricTemplate $metricTemplate): bool
    {
        return Staff::isStaff($user);
    }
}
