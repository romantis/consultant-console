<?php

namespace App\Http\Controllers\Organization;

use App\Enums\EngagementStatus;
use App\Enums\MembershipRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateMetricParticipantRequest;
use App\Models\Engagement;
use App\Models\EngagementParticipant;
use App\Models\Form;
use App\Models\FormAssignment;
use App\Models\FormSubmission;
use App\Models\Membership;
use App\Models\Metric;
use App\Models\MetricParticipant;
use App\Models\Organization;
use App\Models\PendingMembership;
use App\Support\Staff;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EngagementParticipantController extends Controller
{
    public function update(
        UpdateMetricParticipantRequest $request,
        Organization $organization,
        Engagement $engagement,
        Metric $metric,
        MetricParticipant $metricParticipant
    ): RedirectResponse {
        abort_unless($metric->organization_id === $organization->id, 404);
        abort_unless($metric->engagement_id === $engagement->id, 404);
        abort_unless($metricParticipant->metric_id === $metric->id, 404);

        $engagementStatus = $engagement->status instanceof EngagementStatus
            ? $engagement->status
            : EngagementStatus::tryFromLegacy((string) $engagement->status);

        if ($engagementStatus === EngagementStatus::FINALIZED) {
            throw ValidationException::withMessages([
                'engagement' => __('Engagement is finalized and read-only.'),
            ]);
        }

        $this->authorize('update', $metricParticipant);

        $data = $request->validated();

        /** @var EngagementParticipant $engagementParticipant */
        $engagementParticipant = EngagementParticipant::query()
            ->where('engagement_id', $engagement->id)
            ->where('id', $data['engagement_participant_id'])
            ->firstOrFail();

        $engagementParticipant->loadMissing(['membership.user', 'pendingMembership']);
        $responsibility = strtolower((string) $data['responsibility']);
        $this->ensureAssigneeEligibility($engagementParticipant, $responsibility);

        $subjectType = null;
        $subjectId = null;
        $userId = null;

        if ($engagementParticipant->participant_type === EngagementParticipant::TYPE_MEMBERSHIP
            && $engagementParticipant->membership_id) {
            $subjectType = Membership::class;
            $subjectId = $engagementParticipant->membership_id;
            $userId = $engagementParticipant->membership?->user_id;
        } elseif ($engagementParticipant->participant_type === EngagementParticipant::TYPE_PENDING
            && $engagementParticipant->pending_membership_id) {
            $subjectType = PendingMembership::class;
            $subjectId = $engagementParticipant->pending_membership_id;
        }

        $roleTags = array_key_exists('role_tags', $data)
            ? ($data['role_tags'] ?? [])
            : ($metricParticipant->role_tags_json ?? []);

        DB::transaction(function () use ($metricParticipant, $engagementParticipant, $userId, $subjectType, $subjectId, $responsibility, $roleTags) {
            $metricParticipant->forceFill([
                'engagement_participant_id' => $engagementParticipant->id,
                'user_id' => $userId,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'responsibility' => $responsibility,
                'role_tags_json' => $roleTags,
            ])->save();

            $this->syncAssignments($metricParticipant);
        });

        return back()->with('success', __('Metric participant updated.'));
    }

    private function ensureAssigneeEligibility(
        EngagementParticipant $engagementParticipant,
        string $responsibility
    ): void {
        if (! $engagementParticipant->active) {
            throw ValidationException::withMessages([
                'engagement_participant_id' => __('Only active participants can be assigned.'),
            ]);
        }

        $membership = $engagementParticipant->membership;
        $membershipRole = $engagementParticipant->membership_role ?? $membership?->role;

        if ($membershipRole === MembershipRole::VIEWER && $responsibility !== 'observer') {
            throw ValidationException::withMessages([
                'responsibility' => __('View-only participants can only be assigned as observers.'),
            ]);
        }

        if ($engagementParticipant->participant_type === EngagementParticipant::TYPE_PENDING) {
            return;
        }

        $user = $membership?->user;

        if (! $membership || ! $user) {
            throw ValidationException::withMessages([
                'engagement_participant_id' => __('Selected participant is no longer linked to an active user.'),
            ]);
        }

        if (Staff::isStaff($user) && ! in_array($responsibility, ['observer', 'approver'], true)) {
            throw ValidationException::withMessages([
                'responsibility' => __('Staff participants can only be assigned as observers or approvers.'),
            ]);
        }
    }

    private function syncAssignments(MetricParticipant $metricParticipant): void
    {
        $responsibility = strtolower((string) $metricParticipant->responsibility);
        $userId = $metricParticipant->user_id;

        if (! in_array($responsibility, ['contributor', 'approver'], true) || empty($userId)) {
            $metricParticipant->assignments()->delete();

            return;
        }

        $metricParticipant->assignments()
            ->where('user_id', '!=', $userId)
            ->delete();

        $forms = Form::query()
            ->where('metric_id', $metricParticipant->metric_id)
            ->get();

        foreach ($forms as $form) {
            $assignment = FormAssignment::firstOrNew([
                'form_id' => $form->id,
                'user_id' => $userId,
            ]);

            $assignment->fill([
                'metric_id' => $metricParticipant->metric_id,
                'metric_participant_id' => $metricParticipant->id,
                'responsibility' => ucfirst($responsibility),
                'role_tags_json' => $metricParticipant->role_tags_json ?? [],
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
