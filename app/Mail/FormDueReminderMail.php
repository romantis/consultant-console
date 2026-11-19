<?php

namespace App\Mail;

use App\Models\FormAssignment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class FormDueReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public FormAssignment $assignment) {}

    public function build()
    {
        return $this->subject('Reminder: a form is due soon')
            ->markdown('mail.form_due_reminder', [
                'form' => $this->assignment->form,
                'due' => $this->assignment->due_at,
            ]);
    }
}
