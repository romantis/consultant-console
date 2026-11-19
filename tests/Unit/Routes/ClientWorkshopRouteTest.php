<?php

use Illuminate\Routing\Exceptions\UrlGenerationException;
use Tests\TestCase;

uses(TestCase::class);

test('client workshop route name resolves with required parameters', function () {
    $url = route('client.handbook.workshop', [
        'organization' => 'org-1',
        'engagement' => 'eng-1',
    ]);

    expect($url)->toContain('/client/organizations/org-1/engagements/eng-1/workshop');
});

test('client workshop route requires organization and engagement parameters', function () {
    expect(fn () => route('client.handbook.workshop'))
        ->toThrow(UrlGenerationException::class);
});
