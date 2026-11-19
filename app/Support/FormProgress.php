<?php

namespace App\Support;

use App\Models\Form;

class FormProgress
{
    public static function sync(Form $form): void
    {
        $submittedCount = $form->submissions()->whereNotNull('submitted_at')->count();
        $draftCount = $form->submissions()->whereNull('submitted_at')->count();
        $assignmentCount = $form->assignments()->count();
        $totalSlots = $assignmentCount > 0 ? $assignmentCount : max($submittedCount + $draftCount, 1);

        $status = 'Open';
        if ($submittedCount > 0 && $submittedCount < $totalSlots) {
            $status = 'InProgress';
        } elseif ($submittedCount >= $totalSlots && $totalSlots > 0) {
            $status = 'Completed';
        }

        $lastSubmissionAt = $form->submissions()->whereNotNull('submitted_at')->max('submitted_at');

        $form->forceFill([
            'status' => $status,
            'last_submission_at' => $lastSubmissionAt,
        ])->save();
    }
}
