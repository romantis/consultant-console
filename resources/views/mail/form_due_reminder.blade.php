@component('mail::message')
# Reminder

The form **{{ $form->title }}** is due {{ $due?->diffForHumans() ?? 'soon' }}.

@component('mail::button', ['url' => route('forms.show', $form)])
Open Form
@endcomponent

Thanks,<br>
{{ config('app.name') }}
@endcomponent
