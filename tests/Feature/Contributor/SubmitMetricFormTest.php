<?php

use App\Enums\FormAssignmentStatus;
use App\Enums\MembershipRole;
use App\Enums\MetricStatus;
use App\Models\Engagement;
use App\Models\Form;
use App\Models\FormAssignment;
use App\Models\FormSubmission;
use App\Models\Membership;
use App\Models\Metric;
use App\Models\MetricDefinition;
use App\Models\MetricParticipant;
use App\Models\MetricTemplate;
use App\Models\Organization;
use App\Models\ReminderSchedule;
use App\Models\User;
use App\Support\Notifications\ReminderDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

it('allows a contributor to autosave and submit an assigned metric form', function () {
    Mail::fake();

    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->for($organization)->create();

    $template = MetricTemplate::factory()->create([
        'key' => 'net-retention',
        'name' => 'Net Retention',
        'form_schema_json' => [
            'fields' => [
                [
                    'name' => 'retention_rate',
                    'type' => 'number',
                    'label' => 'Retention Rate',
                    'required' => true,
                ],
                [
                    'name' => 'notes',
                    'type' => 'textarea',
                    'label' => 'Notes',
                    'required' => false,
                ],
                [
                    'name' => 'channels',
                    'type' => 'checkbox_group',
                    'label' => 'Channels counted',
                    'required' => true,
                    'options' => ['Inbound', 'Outbound', 'Partner referrals'],
                    'with_other' => true,
                    'other_prompt' => 'Please specify channel',
                ],
                [
                    'name' => 'primary_channel',
                    'type' => 'select',
                    'label' => 'Primary channel',
                    'required' => true,
                    'options' => ['Inbound', 'Outbound', 'Partner referrals'],
                    'with_other' => true,
                    'other_prompt' => 'Which channel?',
                ],
            ],
        ],
    ]);

    $metric = Metric::factory()
        ->for($engagement)
        ->create([
            'organization_id' => $organization->id,
            'metric_template_id' => $template->id,
            'key' => $template->key,
            'name' => $template->name,
            'status' => MetricStatus::AWAITING_FORMS,
        ]);

    $form = Form::create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'metric_id' => $metric->id,
        'template_key' => $template->key,
        'title' => 'Net Retention Survey',
        'schema_json' => $template->form_schema_json,
        'status' => 'Open',
    ]);

    $contributor = User::factory()->create();

    Membership::factory()
        ->for($organization)
        ->for($contributor)
        ->state(['role' => MembershipRole::MEMBER])
        ->create();

    $participant = MetricParticipant::factory()
        ->for($metric)
        ->for($contributor, 'user')
        ->contributor()
        ->create();

    $assignment = FormAssignment::create([
        'form_id' => $form->id,
        'metric_id' => $metric->id,
        'metric_participant_id' => $participant->id,
        'user_id' => $contributor->id,
        'responsibility' => 'Contributor',
        'role_tags_json' => [],
        'due_at' => now()->addDay(),
    ]);

    $schedule = ReminderSchedule::create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'metric_id' => $metric->id,
        'form_assignment_id' => $assignment->id,
        'window' => '24h',
        'channel' => 'email',
        'deliver_at' => now()->subHour(),
        'status' => ReminderSchedule::STATUS_PENDING,
    ]);

    $this->actingAs($contributor)
        ->get(route('forms.show', $form))
        ->assertOk();

    $draftPayload = [
        'retention_rate' => 87.5,
        'notes' => 'Initial draft before review.',
        'channels' => ['Inbound'],
        'primary_channel' => 'Inbound',
    ];

    $this->actingAs($contributor)
        ->put(route('forms.autosave', $form), [
            'answers_json' => $draftPayload,
        ])
        ->assertRedirect(route('forms.show', $form));

    $submission = FormSubmission::where('form_id', $form->id)
        ->where('user_id', $contributor->id)
        ->firstOrFail();

    expect($submission->answers_json)->toMatchArray($draftPayload);
    expect($submission->submitted_at)->toBeNull();
    expect($assignment->refresh()->status)->toBe(FormAssignmentStatus::IN_PROGRESS);

    $finalPayload = [
        'retention_rate' => 92.4,
        'notes' => 'Numbers reviewed and approved.',
        'channels' => ['Inbound', 'Other'],
        'channels__other_text' => 'Local field events',
        'primary_channel' => 'Other',
        'primary_channel__other_text' => 'Partner marketplace',
    ];

    $this->actingAs($contributor)
        ->post(route('forms.submit', $form), [
            'answers_json' => $finalPayload,
        ])
        ->assertRedirect(route('forms.show', $form))
        ->assertSessionHas('success', 'Submitted');

    $submission->refresh();

    expect($submission->submitted_at)->not->toBeNull();
    expect($submission->answers_json)->toMatchArray($finalPayload);
    expect($assignment->refresh()->status)->toBe(FormAssignmentStatus::COMPLETED);

    expect($form->refresh()->status)->toBe('Completed');
    expect($form->last_submission_at)->not->toBeNull();

    $definition = MetricDefinition::where('form_submission_id', $submission->id)->first();

    expect($definition)->not->toBeNull();
    expect($definition->status)->toBe('Submitted');
    expect($definition->definition_json)->toMatchArray($finalPayload);
    expect($definition->normalizedField('retention_rate')['normalized'] ?? null)->toBe(92.4);

    $normalizedChannels = $definition->normalizedField('channels')['normalized'] ?? null;
    expect($normalizedChannels)->toBeArray();
    expect($normalizedChannels)->toMatchArray(['inbound', 'other']);

    $otherMeta = $definition->normalizedField('channels')['other_text'] ?? null;
    expect($otherMeta)->toBe('Local field events');
    expect($definition->normalizedField('channels')['other_text_normalized'] ?? null)->toBe('local field events');

    $primaryChannel = $definition->normalizedField('primary_channel');
    expect($primaryChannel['normalized'] ?? null)->toBe('other');
    expect($primaryChannel['other_text'] ?? null)->toBe('Partner marketplace');
    expect($primaryChannel['other_text_normalized'] ?? null)->toBe('partner marketplace');

    app(ReminderDispatcher::class)->dispatch($schedule->fresh());

    $schedule->refresh();

    expect($schedule->status)->toBe(ReminderSchedule::STATUS_CANCELLED);
    expect($schedule->delivered_at)->not->toBeNull();
});

it('requires contributors to describe the Other checkbox option before submitting', function () {
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->for($organization)->create();

    $template = MetricTemplate::factory()->create([
        'key' => 'channels-audit',
        'name' => 'Channel Audit',
        'form_schema_json' => [
            'fields' => [
                [
                    'name' => 'channels',
                    'type' => 'checkbox_group',
                    'label' => 'Channels reviewed',
                    'required' => true,
                    'options' => ['Inbound', 'Outbound'],
                    'with_other' => true,
                    'other_prompt' => 'Please specify channel',
                ],
            ],
        ],
    ]);

    $metric = Metric::factory()
        ->for($engagement)
        ->create([
            'organization_id' => $organization->id,
            'metric_template_id' => $template->id,
            'key' => $template->key,
            'name' => $template->name,
            'status' => MetricStatus::AWAITING_FORMS,
        ]);

    $form = Form::create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'metric_id' => $metric->id,
        'template_key' => $template->key,
        'title' => 'Channel Audit Survey',
        'schema_json' => $template->form_schema_json,
        'status' => 'Open',
    ]);

    $user = User::factory()->create();

    Membership::factory()
        ->for($organization)
        ->for($user)
        ->state(['role' => MembershipRole::MEMBER])
        ->create();

    $participant = MetricParticipant::factory()
        ->for($metric)
        ->for($user, 'user')
        ->contributor()
        ->create();

    FormAssignment::create([
        'form_id' => $form->id,
        'metric_id' => $metric->id,
        'metric_participant_id' => $participant->id,
        'user_id' => $user->id,
        'responsibility' => 'Contributor',
        'role_tags_json' => [],
        'due_at' => now()->addDay(),
    ]);

    $this->actingAs($user)
        ->post(route('forms.submit', $form), [
            'answers_json' => [
                'channels' => ['Other'],
            ],
        ])
        ->assertSessionHasErrors(['answers_json.channels__other_text']);
});

it('keeps assignment status open when submission fails validation', function () {
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->for($organization)->create();

    $template = MetricTemplate::factory()->create([
        'key' => 'channels-audit',
        'name' => 'Channel Audit',
        'form_schema_json' => [
            'fields' => [
                [
                    'name' => 'channels',
                    'type' => 'checkbox_group',
                    'label' => 'Channels reviewed',
                    'required' => true,
                    'options' => ['Inbound', 'Outbound'],
                    'with_other' => true,
                    'other_prompt' => 'Please specify channel',
                ],
            ],
        ],
    ]);

    $metric = Metric::factory()
        ->for($engagement)
        ->create([
            'organization_id' => $organization->id,
            'metric_template_id' => $template->id,
            'key' => $template->key,
            'name' => $template->name,
            'status' => MetricStatus::AWAITING_FORMS,
        ]);

    $form = Form::create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'metric_id' => $metric->id,
        'template_key' => $template->key,
        'title' => 'Channel Audit Survey',
        'schema_json' => $template->form_schema_json,
        'status' => 'Open',
    ]);

    $user = User::factory()->create();

    Membership::factory()
        ->for($organization)
        ->for($user)
        ->state(['role' => MembershipRole::MEMBER])
        ->create();

    $participant = MetricParticipant::factory()
        ->for($metric)
        ->for($user, 'user')
        ->contributor()
        ->create();

    $assignment = FormAssignment::create([
        'form_id' => $form->id,
        'metric_id' => $metric->id,
        'metric_participant_id' => $participant->id,
        'user_id' => $user->id,
        'responsibility' => 'Contributor',
        'role_tags_json' => [],
        'due_at' => now()->addDay(),
    ]);

    $this->actingAs($user)
        ->post(route('forms.submit', $form), [
            'answers_json' => [
                'channels' => ['Other'],
            ],
        ])
        ->assertSessionHasErrors(['answers_json.channels__other_text']);

    expect($assignment->refresh()->status)->toBe(FormAssignmentStatus::OPEN);
});

it('requires contributors to describe the Other single select option before submitting', function () {
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->for($organization)->create();

    $template = MetricTemplate::factory()->create([
        'key' => 'primary-channel',
        'name' => 'Primary Channel',
        'form_schema_json' => [
            'fields' => [
                [
                    'name' => 'primary_channel',
                    'type' => 'select',
                    'label' => 'Primary channel',
                    'required' => true,
                    'options' => ['Inbound', 'Outbound'],
                    'with_other' => true,
                    'other_prompt' => 'Which channel?',
                ],
            ],
        ],
    ]);

    $metric = Metric::factory()
        ->for($engagement)
        ->create([
            'organization_id' => $organization->id,
            'metric_template_id' => $template->id,
            'key' => $template->key,
            'name' => $template->name,
            'status' => MetricStatus::AWAITING_FORMS,
        ]);

    $form = Form::create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'metric_id' => $metric->id,
        'template_key' => $template->key,
        'title' => 'Primary Channel Survey',
        'schema_json' => $template->form_schema_json,
        'status' => 'Open',
    ]);

    $user = User::factory()->create();

    Membership::factory()
        ->for($organization)
        ->for($user)
        ->state(['role' => MembershipRole::MEMBER])
        ->create();

    $participant = MetricParticipant::factory()
        ->for($metric)
        ->for($user, 'user')
        ->contributor()
        ->create();

    FormAssignment::create([
        'form_id' => $form->id,
        'metric_id' => $metric->id,
        'metric_participant_id' => $participant->id,
        'user_id' => $user->id,
        'responsibility' => 'Contributor',
        'role_tags_json' => [],
        'due_at' => now()->addDay(),
    ]);

    $this->actingAs($user)
        ->post(route('forms.submit', $form), [
            'answers_json' => [
                'primary_channel' => 'Other',
            ],
        ])
        ->assertSessionHasErrors(['answers_json.primary_channel__other_text']);
});
