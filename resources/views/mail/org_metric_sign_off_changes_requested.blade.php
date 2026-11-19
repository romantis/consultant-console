@component('mail::message')
# Changes requested: {{ $metricName }}

Updates are needed before the sign-off for **{{ $metricName }}** at **{{ $organizationName }}** can be approved.

@isset($engagementTitle)
Engagement: {{ $engagementTitle }}
@endisset

@isset($requester)
Requested by {{ $requester }}.
@endisset

@if(! empty($comment))
> {{ $comment }}
@endif

@component('mail::button', ['url' => $url])
Review metric
@endcomponent

Thanks,<br>
{{ config('app.name') }}
@endcomponent
