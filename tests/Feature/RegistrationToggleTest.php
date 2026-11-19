<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('404s register when disabled', function () {
    config()->set('console.allow_public_registration', false);
    $this->get(route('register'))->assertNotFound();
    $this->post(route('register.store'), [])->assertNotFound();
});

it('exposes register when enabled', function () {
    config()->set('console.allow_public_registration', true);
    $this->get(route('register'))->assertOk();
});

it('keeps internal-first mvp feature flags disabled by default', function () {
    config()->set('metric-harmony.features.public_handbook_links', false);
    config()->set('metric-harmony.features.change_proposals', false);
    config()->set('metric-harmony.features.org_metric_overrides', false);
    config()->set('metric-harmony.features.internal_first_manager_signoff_override', false);

    expect(config('metric-harmony.features.public_handbook_links'))->toBeFalse();
    expect(config('metric-harmony.features.change_proposals'))->toBeFalse();
    expect(config('metric-harmony.features.org_metric_overrides'))->toBeFalse();
    expect(config('metric-harmony.features.internal_first_manager_signoff_override'))->toBeFalse();
});
