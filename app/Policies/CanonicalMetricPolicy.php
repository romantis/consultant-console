<?php

namespace App\Policies;

use App\Models\CanonicalMetric;
use App\Models\User;
use App\Support\Staff;

class CanonicalMetricPolicy
{
    public function viewAny(User $user): bool
    {
        return Staff::isStaff($user);
    }

    public function view(User $user, CanonicalMetric $metric): bool
    {
        return Staff::isStaff($user);
    }

    public function create(User $user): bool
    {
        return Staff::isStaff($user);
    }

    public function update(User $user, CanonicalMetric $metric): bool
    {
        return Staff::isStaff($user);
    }

    public function delete(User $user, CanonicalMetric $metric): bool
    {
        return Staff::isStaff($user);
    }
}
