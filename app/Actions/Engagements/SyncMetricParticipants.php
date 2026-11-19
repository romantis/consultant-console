<?php

namespace App\Actions\Engagements;

use App\Enums\EngagementStatus;
use App\Enums\MembershipRole;
use App\Models\Engagement;
use App\Models\EngagementParticipant;
use App\Models\Form;
use App\Models\FormAssignment;
use App\Models\FormSubmission;
use App\Models\Membership;
use App\Models\Metric;
use App\Models\MetricParticipant;
use App\Models\PendingMembership;
use App\Support\Staff;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SyncMetricParticipants
{
    /**
     * @param  array<int, array{
     *     engagement_participant_id: string,
     *     responsibility: string,
     *     role_tags?: array<int, string>
     * }>  $participants
     */
    public function handle(Engagement $engagement, Metric $metric, array $participants): void
    {
        $this->assertMetricEditable($engagement, $metric);

        $resolvedParticipants = $this->resolveParticipants($engagement, collect($participants));
        $this->assertRoleDistribution($resolvedParticipants);

        DB::transaction(function () use ($metric, $resolvedParticipants): void {
            $syncedParticipants = $this->syncMetricParticipants($metric, $resolvedParticipants);
            $this->syncFormAssignments($metric, $syncedParticipants);
        });
    }

    private function assertMetricEditable(Engagement $engagement, Metric $metric): void
    {
        $engagementStatus = $engagement->status instanceof EngagementStatus
            ? $engagement->status
            : EngagementStatus::tryFromLegacy((string) $engagement->status);

        if ($engagementStatus === EngagementStatus::FINALIZED) {
            throw ValidationException::withMessages([
                'engagement' => __('Engagement is finalized and read-only.'),
            ]);
        }

        $metric->loadMissing('finalDefinition');

        if ($metric->finalDefinition?->signed_off_at) {
            throw ValidationException::withMessages([
                'participants' => __('Participant assignments are locked after sign-off.'),
            ]);
        }
    }

    /**
     * @param  Collection<int, array{
     *     engagement_participant_id: string,
     *     responsibility: string,
     *     role_tags?: array<int, string>
     * }>  $participants
     * @return Collection<int, array{
     *     engagement_participant_id: string,
     *     user_id: string|null,
     *     subject_type: class-string<Membership>|class-string<PendingMembership>|null,
     *     subject_id: string|null,
     *     responsibility: string,
     *     role_tags: array<int, string>
     * }>
     */
    private function resolveParticipants(
        Engagement $engagement,
        Collection $participants
    ): Collection {
        $participantReferences = $participants
            ->pluck('engagement_participant_id')
            ->filter(fn ($value) => is_string($value) && $value !== '')
            ->unique()
            ->values();

        $participantIds = $participantReferences
            ->filter(fn (string $reference) => ! $this->isMembershipReference($reference))
            ->values();

        $membershipIds = $participantReferences
            ->map(fn (string $reference) => $this->membershipIdFromReference($reference))
            ->filter(fn ($membershipId) => is_string($membershipId) && $membershipId !== '')
            ->values();

        $engagementParticipantsById = EngagementParticipant::query()
            ->where('engagement_id', $engagement->id)
            ->whereIn('id', $participantIds->all())
            ->with(['membership.user', 'pendingMembership'])
            ->get()
            ->keyBy(fn (EngagementParticipant $participant) => (string) $participant->getKey());

        $engagementParticipantsByMembership = EngagementParticipant::query()
            ->where('engagement_id', $engagement->id)
            ->where('participant_type', EngagementParticipant::TYPE_MEMBERSHIP)
            ->whereIn('membership_id', $membershipIds->all())
            ->with(['membership.user', 'pendingMembership'])
            ->get()
            ->keyBy(fn (EngagementParticipant $participant) => (string) $participant->membership_id);

        $membershipsById = Membership::query()
            ->where('organization_id', $engagement->organization_id)
            ->whereIn('id', $membershipIds->all())
            ->with('user')
            ->get()
            ->keyBy(fn (Membership $membership) => (string) $membership->id);

        return $participants
            ->values()
            ->map(function (array $entry, int $index) use (
                $engagement,
                $engagementParticipantsById,
                $engagementParticipantsByMembership,
                $membershipsById
            ) {
                $participantReference = (string) ($entry['engagement_participant_id'] ?? '');
                $responsibility = strtolower((string) ($entry['responsibility'] ?? ''));

                $engagementParticipant = $this->resolveEngagementParticipant(
                    $engagement,
                    $participantReference,
                    $engagementParticipantsById,
                    $engagementParticipantsByMembership,
                    $membershipsById,
                    $index
                );

                $this->ensureParticipantEligibility(
                    $engagementParticipant,
                    $responsibility,
                    $index
                );

                $engagementParticipantId = (string) $engagementParticipant->getKey();

                $subjectType = null;
                $subjectId = null;
                $userId = null;

                if ($engagementParticipant->participant_type === EngagementParticipant::TYPE_MEMBERSHIP
                    && $engagementParticipant->membership_id) {
                    $subjectType = Membership::class;
                    $subjectId = (string) $engagementParticipant->membership_id;
                    $userId = $engagementParticipant->membership?->user_id
                        ? (string) $engagementParticipant->membership?->user_id
                        : null;
                } elseif ($engagementParticipant->participant_type === EngagementParticipant::TYPE_PENDING
                    && $engagementParticipant->pending_membership_id) {
                    $subjectType = PendingMembership::class;
                    $subjectId = (string) $engagementParticipant->pending_membership_id;
                }

                $roleTags = is_array($entry['role_tags'] ?? null)
                    ? collect($entry['role_tags'])
                        ->filter(fn ($tag) => is_string($tag) && trim($tag) !== '')
                        ->map(fn (string $tag) => trim($tag))
                        ->values()
                        ->all()
                    : [];

                return [
                    'engagement_participant_id' => $engagementParticipantId,
                    'user_id' => $userId,
                    'subject_type' => $subjectType,
                    'subject_id' => $subjectId,
                    'responsibility' => $responsibility,
                    'role_tags' => $roleTags,
                ];
            });
    }

    private function resolveEngagementParticipant(
        Engagement $engagement,
        string $participantReference,
        Collection $engagementParticipantsById,
        Collection $engagementParticipantsByMembership,
        Collection $membershipsById,
        int $index
    ): EngagementParticipant {
        if (! $this->isMembershipReference($participantReference)) {
            /** @var EngagementParticipant|null $engagementParticipant */
            $engagementParticipant = $engagementParticipantsById->get($participantReference);

            if ($engagementParticipant) {
                return $engagementParticipant;
            }

            throw ValidationException::withMessages([
                "participants.{$index}.engagement_participant_id" => __('Selected participant could not be found.'),
            ]);
        }

        $membershipId = $this->membershipIdFromReference($participantReference);

        if (! $membershipId) {
            throw ValidationException::withMessages([
                "participants.{$index}.engagement_participant_id" => __('Selected participant could not be found.'),
            ]);
        }

        /** @var EngagementParticipant|null $existingParticipant */
        $existingParticipant = $engagementParticipantsByMembership->get($membershipId);
        if ($existingParticipant) {
            return $existingParticipant;
        }

        /** @var Membership|null $membership */
        $membership = $membershipsById->get($membershipId);
        $user = $membership?->user;

        if (! $membership || ! $user) {
            throw ValidationException::withMessages([
                "participants.{$index}.engagement_participant_id" => __('Selected participant could not be found.'),
            ]);
        }

        $engagementParticipant = EngagementParticipant::updateOrCreate(
            [
                'engagement_id' => $engagement->id,
                'participant_type' => EngagementParticipant::TYPE_MEMBERSHIP,
                'membership_id' => $membership->id,
            ],
            [
                'pending_membership_id' => null,
                'membership_role' => $membership->role?->value,
                'active' => true,
            ]
        );

        $engagementParticipant->setRelation('membership', $membership);
        $engagementParticipant->setRelation('pendingMembership', null);

        $engagementParticipantsById->put((string) $engagementParticipant->getKey(), $engagementParticipant);
        $engagementParticipantsByMembership->put((string) $membership->id, $engagementParticipant);

        return $engagementParticipant;
    }

    private function isMembershipReference(string $reference): bool
    {
        return str_starts_with($reference, 'membership:');
    }

    private function membershipIdFromReference(string $reference): ?string
    {
        if (! $this->isMembershipReference($reference)) {
            return null;
        }

        $membershipId = trim(substr($reference, strlen('membership:')));

        return $membershipId !== '' ? $membershipId : null;
    }

    private function ensureParticipantEligibility(
        EngagementParticipant $engagementParticipant,
        string $responsibility,
        int $index
    ): void {
        if (! $engagementParticipant->active) {
            throw ValidationException::withMessages([
                "participants.{$index}.engagement_participant_id" => __('Only active participants can be assigned.'),
            ]);
        }

        $membershipRole = $engagementParticipant->membership_role;

        if ($membershipRole === MembershipRole::VIEWER && $responsibility !== 'observer') {
            throw ValidationException::withMessages([
                "participants.{$index}.responsibility" => __('View-only participants can only be assigned as observers.'),
            ]);
        }

        if ($engagementParticipant->participant_type === EngagementParticipant::TYPE_MEMBERSHIP) {
            $membership = $engagementParticipant->membership;
            $user = $membership?->user;

            if (! $membership || ! $user) {
                throw ValidationException::withMessages([
                    "participants.{$index}.engagement_participant_id" => __('Selected participant is no longer linked to an active user.'),
                ]);
            }

            if (Staff::isStaff($user) && ! in_array($responsibility, ['observer', 'approver'], true)) {
                throw ValidationException::withMessages([
                    "participants.{$index}.responsibility" => __('Staff participants can only be assigned as observers or approvers.'),
                ]);
            }
        }
    }

    /**
     * @param  Collection<int, array{responsibility: string}>  $participants
     */
    private function assertRoleDistribution(Collection $participants): void
    {
        $contributors = $participants->where('responsibility', 'contributor')->count();
        $approvers = $participants->where('responsibility', 'approver')->count();
        $observers = $participants->where('responsibility', 'observer')->count();

        $errors = [];

        if ($contributors < 2) {
            $errors['participants'][] = __('Each metric must include at least two contributors.');
        }

        if ($approvers !== 1) {
            $errors['participants'][] = __('Each metric must include exactly one approver.');
        }

        if ($observers > 5) {
            $errors['participants'][] = __('Metrics cannot include more than five observers.');
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @param  Collection<int, array{
     *     engagement_participant_id: string,
     *     user_id: string|null,
     *     subject_type: class-string<Membership>|class-string<PendingMembership>|null,
     *     subject_id: string|null,
     *     responsibility: string,
     *     role_tags: array<int, string>
     * }>  $participants
     * @return Collection<int, MetricParticipant>
     */
    private function syncMetricParticipants(Metric $metric, Collection $participants): Collection
    {
        $existingByParticipant = MetricParticipant::query()
            ->where('metric_id', $metric->id)
            ->with('assignments')
            ->get()
            ->groupBy(fn (MetricParticipant $participant) => (string) $participant->engagement_participant_id);

        $synced = collect();

        foreach ($participants as $entry) {
            /** @var Collection<int, MetricParticipant> $current */
            $current = $existingByParticipant->get($entry['engagement_participant_id'], collect());

            /** @var MetricParticipant|null $primary */
            $primary = $current->first();

            if (! $primary) {
                $primary = MetricParticipant::create([
                    'metric_id' => $metric->id,
                    'engagement_participant_id' => $entry['engagement_participant_id'],
                    'user_id' => $entry['user_id'],
                    'subject_type' => $entry['subject_type'],
                    'subject_id' => $entry['subject_id'],
                    'responsibility' => $entry['responsibility'],
                    'role_tags_json' => $entry['role_tags'],
                ]);
            } else {
                $primary->forceFill([
                    'user_id' => $entry['user_id'],
                    'subject_type' => $entry['subject_type'],
                    'subject_id' => $entry['subject_id'],
                    'responsibility' => $entry['responsibility'],
                    'role_tags_json' => $entry['role_tags'],
                ])->save();

                $current->slice(1)->each(function (MetricParticipant $duplicate): void {
                    $duplicate->assignments()->delete();
                    $duplicate->delete();
                });
            }

            $synced->push($primary);
        }

        $desiredParticipantIds = $participants
            ->pluck('engagement_participant_id')
            ->values()
            ->all();

        $existingByParticipant->each(function (Collection $group, string $engagementParticipantId) use ($desiredParticipantIds): void {
            if (in_array($engagementParticipantId, $desiredParticipantIds, true)) {
                return;
            }

            $group->each(function (MetricParticipant $participant): void {
                $participant->assignments()->delete();
                $participant->delete();
            });
        });

        return $synced->values();
    }

    /**
     * @param  Collection<int, MetricParticipant>  $participants
     */
    private function syncFormAssignments(Metric $metric, Collection $participants): void
    {
        $forms = Form::query()
            ->where('metric_id', $metric->id)
            ->get(['id']);

        $participantsByUser = $participants
            ->filter(fn (MetricParticipant $participant) => in_array(
                strtolower((string) $participant->responsibility),
                ['contributor', 'approver'],
                true
            ) && filled($participant->user_id))
            ->keyBy(fn (MetricParticipant $participant) => (string) $participant->user_id);

        foreach ($forms as $form) {
            $assignments = FormAssignment::query()
                ->where('form_id', $form->id)
                ->where('metric_id', $metric->id)
                ->get()
                ->groupBy(fn (FormAssignment $assignment) => (string) $assignment->user_id);

            $desiredUserIds = $participantsByUser->keys()->all();

            $assignments->each(function (Collection $group, string $userId) use ($desiredUserIds): void {
                if (in_array($userId, $desiredUserIds, true)) {
                    $group->slice(1)->each(fn (FormAssignment $assignment) => $assignment->delete());

                    return;
                }

                $group->each(fn (FormAssignment $assignment) => $assignment->delete());
            });

            foreach ($participantsByUser as $userId => $participant) {
                /** @var Collection<int, FormAssignment> $existingAssignments */
                $existingAssignments = $assignments->get((string) $userId, collect());
                /** @var FormAssignment|null $assignment */
                $assignment = $existingAssignments->first();

                if (! $assignment) {
                    $assignment = new FormAssignment([
                        'form_id' => $form->id,
                        'user_id' => $userId,
                    ]);
                }

                $assignment->fill([
                    'metric_id' => $metric->id,
                    'metric_participant_id' => $participant->id,
                    'responsibility' => ucfirst(strtolower((string) $participant->responsibility)),
                    'role_tags_json' => $participant->role_tags_json ?? [],
                ]);

                if (! $assignment->exists || $assignment->isDirty()) {
                    $assignment->save();
                }

                FormSubmission::firstOrCreate([
                    'form_id' => $form->id,
                    'user_id' => $userId,
                ]);
            }
        }
    }
}
