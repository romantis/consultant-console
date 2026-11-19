<?php

it('registers engagement workshop session routes', function () {
    expect(route('org.engagements.workshop-sessions.index', [
        'organization' => 'org-1',
        'engagement' => 'eng-1',
    ], false))->toBe('/organizations/org-1/engagements/eng-1/workshop-sessions');

    expect(route('org.engagements.workshop-sessions.start', [
        'organization' => 'org-1',
        'engagement' => 'eng-1',
        'session' => 'ses-1',
    ], false))->toBe('/organizations/org-1/engagements/eng-1/workshop-sessions/ses-1/start');
});

it('requires authentication for engagement workshop session routes', function () {
    $this->get('/organizations/org-1/engagements/eng-1/workshop-sessions')
        ->assertRedirect('/login');
});
