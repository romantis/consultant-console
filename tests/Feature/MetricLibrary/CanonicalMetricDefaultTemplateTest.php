<?php

use App\Enums\MembershipRole;
use App\Models\CanonicalMetric;
use App\Models\Membership;
use App\Models\MetricTemplate;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeMetricLibraryStaffUser(): User
{
    $user = User::factory()->create();
    $internal = Organization::factory()->create(['is_internal' => true]);

    Membership::factory()->create([
        'organization_id' => $internal->id,
        'user_id' => $user->id,
        'role' => MembershipRole::ADMIN,
    ]);

    return $user;
}

it('rejects default templates that do not belong to the canonical metric', function () {
    $user = makeMetricLibraryStaffUser();

    $canonicalPrimary = CanonicalMetric::factory()->create([
        'key' => 'active_users',
        'name' => 'Active Users',
    ]);

    $canonicalSecondary = CanonicalMetric::factory()->create([
        'key' => 'mrr',
        'name' => 'Monthly Recurring Revenue',
    ]);

    $foreignTemplate = MetricTemplate::factory()->create([
        'canonical_metric_id' => $canonicalSecondary->id,
        'key' => 'mrr-core',
        'name' => 'MRR Core',
    ]);

    $this->actingAs($user);

    $response = $this->patch(route('metric-library.update', $canonicalPrimary), [
        'default_metric_template_id' => (string) $foreignTemplate->id,
    ]);

    $response->assertSessionHasErrors('default_metric_template_id');
});
