<?php

namespace Database\Seeders\StorySeeds;

use App\Enums\MembershipRole;
use App\Enums\StakeholderRole;
use App\Models\Engagement;
use App\Models\EngagementParticipant;
use App\Models\Form;
use App\Models\FormAssignment;
use App\Models\Membership;
use App\Models\Metric;
use App\Models\MetricParticipant;
use App\Models\PendingMembership;
use Illuminate\Database\Seeder;

abstract class BaseStorySeeder extends Seeder
{
    protected StorySeedContext $context;

    public function __construct()
    {
        $this->context = new StorySeedContext;
    }

    protected function createMembershipParticipant(
        Engagement $engagement,
        Membership $membership,
        StakeholderRole $stakeholderRole
    ): EngagementParticipant {
        $role = $membership->role instanceof MembershipRole
            ? $membership->role
            : MembershipRole::from((string) $membership->role);

        return EngagementParticipant::updateOrCreate(
            [
                'engagement_id' => $engagement->id,
                'membership_id' => $membership->id,
            ],
            [
                'participant_type' => EngagementParticipant::TYPE_MEMBERSHIP,
                'membership_role' => $role,
                'stakeholder_role' => $stakeholderRole,
                'active' => true,
            ]
        );
    }

    protected function createPendingParticipant(
        Engagement $engagement,
        PendingMembership $pendingMembership,
        StakeholderRole $stakeholderRole
    ): EngagementParticipant {
        return EngagementParticipant::updateOrCreate(
            [
                'engagement_id' => $engagement->id,
                'pending_membership_id' => $pendingMembership->id,
            ],
            [
                'participant_type' => EngagementParticipant::TYPE_PENDING,
                'membership_role' => $pendingMembership->role,
                'stakeholder_role' => $stakeholderRole,
                'active' => true,
            ]
        );
    }

    /**
     * @param  array<int, string>  $tags
     */
    protected function attachMetricParticipant(
        Metric $metric,
        EngagementParticipant $participant,
        string $responsibility,
        array $tags = [],
        ?Membership $membership = null,
        ?PendingMembership $pending = null
    ): MetricParticipant {
        $metric->loadMissing('engagement');

        $assignmentRole = strtolower($responsibility);
        $subjectType = $membership ? Membership::class : ($pending ? PendingMembership::class : null);
        $subjectId = $membership?->id ?? $pending?->id;

        return MetricParticipant::updateOrCreate(
            [
                'metric_id' => $metric->id,
                'engagement_participant_id' => $participant->id,
                'responsibility' => $assignmentRole,
            ],
            [
                'user_id' => $membership?->user_id,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'role_tags_json' => $tags,
            ]
        );
    }

    protected function ensureForm(Metric $metric, Engagement $engagement, array $overrides = []): Form
    {
        $metric->loadMissing('template');

        return Form::updateOrCreate(
            ['metric_id' => $metric->id],
            array_merge([
                'organization_id' => $engagement->organization_id,
                'engagement_id' => $engagement->id,
                'template_key' => $metric->template?->key ?? 'custom',
                'title' => $metric->name.' — Definition',
                'schema_json' => $metric->template?->form_schema_json ?? ['fields' => []],
                'status' => 'Open',
            ], $overrides)
        );
    }

    protected function clearPreparedFacilitationPlan(Metric $metric): void
    {
        $metric->facilitationBrief()->delete();
        $metric->unsetRelation('facilitationBrief');
    }

    protected function createAssignment(Form $form, MetricParticipant $participant, array $overrides = []): FormAssignment
    {
        $defaultResponsibility = $participant->responsibility
            ? ucfirst($participant->responsibility)
            : 'Contributor';

        return FormAssignment::updateOrCreate(
            [
                'form_id' => $form->id,
                'metric_participant_id' => $participant->id,
            ],
            array_merge([
                'metric_id' => $form->metric_id,
                'user_id' => $participant->user_id,
                'responsibility' => $defaultResponsibility,
                'role_tags_json' => $participant->role_tags_json ?? [],
                'due_at' => now()->addDays(5),
            ], $overrides)
        );
    }
}
