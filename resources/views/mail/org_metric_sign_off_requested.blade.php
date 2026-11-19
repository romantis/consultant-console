@component('mail::message')
# Sign-off requested: {{ $metricName }}

A sign-off request for **{{ $metricName }}** at **{{ $organizationName }}** is ready for review.

@isset($engagementTitle)
Engagement: {{ $engagementTitle }}
@endisset

@isset($initiator)
Requested by {{ $initiator }}.
@endisset

@if(! empty($message))
> {{ $message }}
@endif

@component('mail::button', ['url' => $url])
Review sign-off
@endcomponent

Thanks,<br>
{{ config('app.name') }}
@endcomponent
