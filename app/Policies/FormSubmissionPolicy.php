<?php

namespace App\Policies;

use App\Enums\MembershipRole;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\User;
use App\Support\ImpersonationGuard;
use App\Support\Staff;

class FormSubmissionPolicy
{
    public function view(User $user, FormSubmission $submission): bool
    {
        /** @var Form|null $form */
        $form = $submission->form()->first();
        abort_unless($form, 404);

        if (Staff::isStaffOwner($user) || Staff::isStaffAdmin($user)) {
            return true;
        }

        if (Staff::canOperateAssignedDelivery($user, $form->organization_id)) {
            return true;
        }

        return ImpersonationGuard::resolve($user, $form->organization_id)
            ->allowsAtLeast(MembershipRole::VIEWER);
    }

    public function save(User $user, FormSubmission $submission): bool
    {
        /** @var Form|null $form */
        $form = $submission->form()->first();
        abort_unless($form, 404);

        $assignment = $form->assignments()
            ->where('user_id', $user->id)
            ->first();

        if (! $assignment) {
            return false;
        }

        return mb_strtolower((string) $assignment->responsibility) === 'contributor';
    }

    public function submit(User $user, FormSubmission $submission): bool
    {
        return $this->save($user, $submission);
    }
}
