<?php

use App\Enums\MembershipRole;
use App\Models\Engagement;
use App\Models\Form;
use App\Models\FormAssignment;
use App\Models\FormSubmission;
use App\Models\Membership;
use App\Models\Metric;
use App\Models\MetricParticipant;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

it('throttles autosave requests made in rapid succession', function () {
    Carbon::setTestNow('2025-01-01 08:00:00');

    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->for($organization)->create();
    $metric = Metric::factory()->for($engagement)->create([
        'organization_id' => $organization->id,
    ]);

    $form = Form::create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'metric_id' => $metric->id,
        'template_key' => 'retention',
        'title' => 'Net Retention',
        'schema_json' => [
            'fields' => [
                [
                    'name' => 'retention_rate',
                    'type' => 'number',
                    'label' => 'Retention Rate',
                    'required' => true,
                ],
            ],
        ],
    ]);

    $contributor = User::factory()->create();

    Membership::factory()
        ->for($organization)
        ->for($contributor)
        ->state(['role' => MembershipRole::ADMIN])
        ->create();

    $participant = MetricParticipant::factory()
        ->for($metric)
        ->for($contributor, 'user')
        ->contributor()
        ->create();

    FormAssignment::factory()
        ->for($form, 'form')
        ->for($metric, 'metric')
        ->for($participant, 'participant')
        ->for($contributor, 'user')
        ->create();

    $key = sprintf('form-autosave:%s:%s', $form->id, $contributor->id);
    RateLimiter::clear($key);

    $first = $this->actingAs($contributor)->putJson(route('forms.autosave', $form), [
        'answers_json' => ['retention_rate' => 87],
    ]);

    $first->assertOk()->assertJson([
        'saved' => true,
        'submitted' => false,
    ]);

    $submission = FormSubmission::where('form_id', $form->id)
        ->where('user_id', $contributor->id)
        ->firstOrFail();

    expect($submission->answers_json['retention_rate'] ?? null)->toBe(87);

    $second = $this->actingAs($contributor)->putJson(route('forms.autosave', $form), [
        'answers_json' => ['retention_rate' => 88],
    ]);

    $second->assertStatus(409)->assertJson([
        'message' => 'Autosave temporarily throttled.',
    ]);

    expect($second->headers->get('Retry-After'))->toBe('1');

    Carbon::setTestNow(Carbon::now()->addSeconds(2));

    $third = $this->actingAs($contributor)->putJson(route('forms.autosave', $form), [
        'answers_json' => ['retention_rate' => 90],
    ]);

    $third->assertOk();

    $submission->refresh();

    expect($submission->answers_json['retention_rate'] ?? null)->toBe(90);

    Carbon::setTestNow();
    RateLimiter::clear($key);
});
