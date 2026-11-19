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
use App\Support\Impersonation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

it('shows only contributor assignments with summary data on the forms page', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->for($organization)->create();
    $metric = Metric::factory()->for($organization)->for($engagement)->create();
    $overdueMetric = Metric::factory()->for($organization)->for($engagement)->create();
    $submittedMetric = Metric::factory()->for($organization)->for($engagement)->create();
    $approverMetric = Metric::factory()->for($organization)->for($engagement)->create();

    Membership::factory()->for($organization)->for($user, 'user')->role(MembershipRole::MEMBER)->create();

    $contributorParticipant = MetricParticipant::factory()
        ->for($metric)
        ->for($user, 'user')
        ->contributor()
        ->create();
    $overdueParticipant = MetricParticipant::factory()
        ->for($overdueMetric)
        ->for($user, 'user')
        ->contributor()
        ->create();
    $submittedParticipant = MetricParticipant::factory()
        ->for($submittedMetric)
        ->for($user, 'user')
        ->contributor()
        ->create();

    $form = Form::factory()->for($organization)->for($engagement)->for($metric)->create();
    FormAssignment::factory()
        ->for($form, 'form')
        ->for($metric, 'metric')
        ->for($contributorParticipant, 'participant')
        ->for($user, 'user')
        ->create([
            'due_at' => now()->addDay(),
        ]);

    $overdueForm = Form::factory()->for($organization)->for($engagement)->for($overdueMetric)->create();
    FormAssignment::factory()
        ->for($overdueForm, 'form')
        ->for($overdueMetric, 'metric')
        ->for($overdueParticipant, 'participant')
        ->for($user, 'user')
        ->create([
            'due_at' => now()->subDay(),
        ]);

    $submittedForm = Form::factory()->for($organization)->for($engagement)->for($submittedMetric)->create();
    FormAssignment::factory()
        ->for($submittedForm, 'form')
        ->for($submittedMetric, 'metric')
        ->for($submittedParticipant, 'participant')
        ->for($user, 'user')
        ->create([
            'due_at' => now()->addDays(3),
        ]);

    FormSubmission::factory()->submitted()->create([
        'form_id' => $submittedForm->id,
        'user_id' => $user->id,
    ]);

    // Approver assignment should be excluded.
    $approverParticipant = MetricParticipant::factory()
        ->for($approverMetric)
        ->for($user, 'user')
        ->approver()
        ->create();

    $approverForm = Form::factory()->for($organization)->for($engagement)->for($approverMetric)->create();
    FormAssignment::factory()
        ->for($approverForm, 'form')
        ->for($approverMetric, 'metric')
        ->for($approverParticipant, 'participant')
        ->for($user, 'user')
        ->create([
            'due_at' => now()->addHours(6),
            'responsibility' => 'Approver',
        ]);

    $this->actingAs($user)
        ->get(route('client.forms.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Client/Forms')
            ->where('summary.total', 3)
            ->where('summary.open', 2)
            ->where('summary.due_soon', 1)
            ->where('summary.overdue', 1)
            ->has('assignments', 3)
            ->where('assignments', function ($assignments) use ($metric, $overdueMetric, $submittedMetric) {
                $expected = collect([$metric, $overdueMetric, $submittedMetric])
                    ->map(fn (Metric $row) => (string) $row->id)
                    ->sort()
                    ->values()
                    ->all();

                $actual = collect($assignments)
                    ->pluck('metric.id')
                    ->filter()
                    ->sort()
                    ->values()
                    ->all();

                return $actual === $expected;
            })
        );
});

it('excludes internal organization assignments from client forms for direct internal viewers', function () {
    $user = User::factory()->create();
    $internal = makeInternalOrg();
    $engagement = Engagement::factory()->for($internal)->create();
    $metric = Metric::factory()->for($internal)->for($engagement)->create();

    Membership::factory()
        ->for($internal, 'organization')
        ->for($user, 'user')
        ->role(MembershipRole::VIEWER)
        ->create();

    $participant = MetricParticipant::factory()
        ->for($metric)
        ->for($user, 'user')
        ->contributor()
        ->create();

    $form = Form::factory()
        ->for($internal)
        ->for($engagement)
        ->for($metric)
        ->create();

    FormAssignment::factory()
        ->for($form, 'form')
        ->for($metric, 'metric')
        ->for($participant, 'participant')
        ->for($user, 'user')
        ->create();

    $this->actingAs($user)
        ->withUnencryptedCookie(Impersonation::ACTIVE_ORGANIZATION_COOKIE, (string) $internal->id)
        ->get(route('client.forms.index'))
        ->assertOk()
        ->assertCookieExpired(Impersonation::ACTIVE_ORGANIZATION_COOKIE)
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Client/Forms')
            ->where('summary.total', 0)
            ->where('summary.open', 0)
            ->where('summary.due_soon', 0)
            ->where('summary.overdue', 0)
            ->has('assignments', 0)
        );
});
