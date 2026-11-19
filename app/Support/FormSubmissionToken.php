<?php

namespace App\Support;

use App\Models\FormSubmission;

class FormSubmissionToken
{
    public static function make(FormSubmission $submission): string
    {
        $payload = implode('|', [
            $submission->id,
            $submission->form_id,
            $submission->user_id,
        ]);

        $key = (string) config('app.key');

        return hash_hmac('sha256', $payload, $key);
    }

    public static function verify(FormSubmission $submission, ?string $token): bool
    {
        if (! $token) {
            return false;
        }

        return hash_equals(static::make($submission), $token);
    }
}
