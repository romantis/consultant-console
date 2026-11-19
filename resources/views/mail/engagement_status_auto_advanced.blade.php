@component('mail::message')
# Engagement status updated

The engagement **{{ $engagementTitle }}** at **{{ $organizationName }}** advanced from **{{ $fromStatus }}** to **{{ $toStatus }}** automatically after workshop readiness conditions were met.

@isset($formsTotal)
Workshop-ready metrics: {{ $formsTotal }}
@endisset

@component('mail::button', ['url' => $url])
View engagement
@endcomponent

Thanks,<br>
{{ config('app.name') }}
@endcomponent
