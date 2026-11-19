<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

use App\Enums\MembershipRole;
use App\Models\Organization;
use App\Support\Impersonation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function makeInternalOrg(): Organization
{
    return Organization::factory()->create([
        'name' => 'Metric Harmony (Internal)',
        'slug' => 'metric-harmony',
        'is_internal' => true,
    ]);
}

/**
 * Build a reusable impersonation session payload for Pest tests.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function impersonationSessionPayload(
    Organization|string $organization,
    ?MembershipRole $role = null,
    array $overrides = [],
): array {
    $organizationId = $organization instanceof Organization
        ? (string) $organization->getKey()
        : (string) $organization;

    $payload = [
        Impersonation::SESSION_KEY => $organizationId,
    ];

    if ($role) {
        $payload[Impersonation::SESSION_ROLE_KEY] = $role->value;
    }

    return array_merge($payload, $overrides);
}
