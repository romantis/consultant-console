<?php

namespace App\Http\Controllers\Organization;

use App\Actions\StaffOperatorAssignments\GrantStaffOperatorAssignmentAction;
use App\Actions\StaffOperatorAssignments\RevokeStaffOperatorAssignmentAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\StaffOperatorAssignment\DestroyStaffOperatorAssignmentRequest;
use App\Http\Requests\StaffOperatorAssignment\StoreStaffOperatorAssignmentRequest;
use App\Models\Organization;
use App\Models\StaffOperatorAssignment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class StaffOperatorAssignmentController extends Controller
{
    public function store(
        StoreStaffOperatorAssignmentRequest $request,
        Organization $organization,
        GrantStaffOperatorAssignmentAction $grantStaffOperatorAssignmentAction
    ): RedirectResponse {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $data = $request->validated();
        $targetUser = User::query()->findOrFail($data['user_id']);

        try {
            $grantStaffOperatorAssignmentAction->handle(
                $targetUser,
                $organization,
                $actor,
                $data['reason'] ?? null,
                $data['notes'] ?? null,
            );
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        return back()->with('success', __('Operator assignment granted.'));
    }

    public function destroy(
        DestroyStaffOperatorAssignmentRequest $request,
        Organization $organization,
        StaffOperatorAssignment $assignment,
        RevokeStaffOperatorAssignmentAction $revokeStaffOperatorAssignmentAction
    ): RedirectResponse {
        abort_unless($assignment->organization_id === $organization->id, 404);

        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $data = $request->validated();

        try {
            $revokeStaffOperatorAssignmentAction->handle(
                $assignment,
                $actor,
                $data['reason'] ?? null,
                $data['notes'] ?? null,
            );
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        return back()->with('success', __('Operator assignment revoked.'));
    }
}
