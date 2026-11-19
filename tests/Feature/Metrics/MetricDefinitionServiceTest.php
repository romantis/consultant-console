<?php

use App\Enums\MembershipRole;
use App\Enums\MetricStatus;
use App\Models\Engagement;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\Membership;
use App\Models\Metric;
use App\Models\MetricTemplate;
use App\Models\Organization;
use App\Models\User;
use App\Support\Metrics\MetricDefinitionService;
use Database\Seeders\MetricTemplateSeeder;

it('creates a metric and discrepancy snapshot when submissions diverge', function () {
    $organization = Organization::factory()->create();
    $template = MetricTemplate::factory()->create([
        'form_schema_json' => [
            'fields' => [
                ['name' => 'value', 'type' => 'number', 'label' => 'Metric Value', 'required' => true],
            ],
        ],
    ]);

    $engagement = Engagement::factory()->create(['organization_id' => $organization->id]);
    $metric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'metric_template_id' => $template->id,
    ]);

    $form = Form::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'metric_id' => $metric->id,
        'template_key' => $template->key,
        'schema_json' => $template->form_schema_json,
    ]);

    [$alice, $bob] = User::factory()->count(2)->create();

    Membership::factory()->create(['organization_id' => $organization->id, 'user_id' => $alice->id, 'role' => MembershipRole::ADMIN]);
    Membership::factory()->create(['organization_id' => $organization->id, 'user_id' => $bob->id, 'role' => MembershipRole::MEMBER]);

    $service = new MetricDefinitionService;

    $submissionA = FormSubmission::factory()->submitted()->create([
        'form_id' => $form->id,
        'user_id' => $alice->id,
        'answers_json' => ['value' => 100],
    ]);

    $submissionB = FormSubmission::factory()->submitted()->create([
        'form_id' => $form->id,
        'user_id' => $bob->id,
        'answers_json' => ['value' => 160],
    ]);

    $service->recordFromSubmission($submissionA);
    $service->recordFromSubmission($submissionB);

    $metric = Metric::first();
    expect($metric)->not->toBeNull()
        ->and($metric->status)->toBe(MetricStatus::IN_REVIEW)
        ->and($metric->discrepancies()->count())->toBe(1);

    $snapshot = $metric->discrepancies()->first()->values_snapshot;
    expect($snapshot['values'])->toHaveCount(2);
    expect($metric->finalDefinition)->not->toBeNull();
});

it('keeps metrics in workshop when submissions align within tolerance', function () {
    $organization = Organization::factory()->create();
    $template = MetricTemplate::factory()->create([
        'form_schema_json' => [
            'fields' => [
                ['name' => 'value', 'type' => 'number', 'label' => 'Metric Value', 'required' => true],
            ],
        ],
    ]);

    $engagement = Engagement::factory()->create(['organization_id' => $organization->id]);
    $metric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'metric_template_id' => $template->id,
    ]);

    $form = Form::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'metric_id' => $metric->id,
        'template_key' => $template->key,
        'schema_json' => $template->form_schema_json,
    ]);

    [$alice, $bob] = User::factory()->count(2)->create();
    Membership::factory()->create(['organization_id' => $organization->id, 'user_id' => $alice->id, 'role' => MembershipRole::ADMIN]);
    Membership::factory()->create(['organization_id' => $organization->id, 'user_id' => $bob->id, 'role' => MembershipRole::MEMBER]);

    $service = new MetricDefinitionService;

    $submission = FormSubmission::factory()->submitted()->create([
        'form_id' => $form->id,
        'user_id' => $alice->id,
        'answers_json' => ['value' => 100],
    ]);

    $service->recordFromSubmission($submission);

    $metric = Metric::first();
    expect($metric->status)->toBe(MetricStatus::IN_REVIEW);

    $second = FormSubmission::factory()->submitted()->create([
        'form_id' => $form->id,
        'user_id' => $bob->id,
        'answers_json' => ['value' => 102],
    ]);

    $service->recordFromSubmission($second);
    $metric->refresh();

    expect($metric->discrepancies()->count())->toBe(0)
        ->and($metric->status)->toBe(MetricStatus::IN_WORKSHOP);
});

it('detects attribution conflicts while keeping attribution notes contextual', function () {
    $this->seed(MetricTemplateSeeder::class);

    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->create(['organization_id' => $organization->id]);
    $template = MetricTemplate::query()->where('key', 'conversion_rate')->firstOrFail();
    $metric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'metric_template_id' => $template->id,
    ]);
    $form = Form::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'metric_id' => $metric->id,
        'template_key' => $template->key,
        'schema_json' => $template->form_schema_json,
    ]);

    [$marketingUser, $productUser] = User::factory()->count(2)->create();
    Membership::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $marketingUser->id,
        'role' => MembershipRole::ADMIN,
    ]);
    Membership::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $productUser->id,
        'role' => MembershipRole::MEMBER,
    ]);

    $service = new MetricDefinitionService;
    $marketingDefinition = $service->recordFromSubmission(
        FormSubmission::factory()->submitted()->create([
            'form_id' => $form->id,
            'user_id' => $marketingUser->id,
            'answers_json' => [
                'attribution_model' => 'multi_touch',
                'attribution_model_notes' => 'Ninety-day weighted campaign model.',
            ],
        ])
    );
    $service->recordFromSubmission(
        FormSubmission::factory()->submitted()->create([
            'form_id' => $form->id,
            'user_id' => $productUser->id,
            'answers_json' => [
                'attribution_model' => 'no_model',
                'attribution_model_notes' => 'Product reporting does not allocate source credit.',
            ],
        ])
    );

    expect($marketingDefinition->normalized_json['fields']['attribution_model']['normalized'] ?? null)
        ->toBe('multi_touch')
        ->and($marketingDefinition->normalized_json['meta']['attribution_model_notes']['normalized'] ?? null)
        ->toBe('ninety-day weighted campaign model.')
        ->and($metric->discrepancies()->pluck('field_key')->all())
        ->toBe(['attribution_model']);
});
