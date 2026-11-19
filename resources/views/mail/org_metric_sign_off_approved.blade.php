@component('mail::message')
# Metric signed off: {{ $metricName }}

The metric **{{ $metricName }}** at **{{ $organizationName }}** has been signed off.

@isset($engagementTitle)
Engagement: {{ $engagementTitle }}
@endisset

@isset($approver)
Signed off by {{ $approver }}.
@endisset

@if(! empty($comment))
> {{ $comment }}
@endif

@component('mail::button', ['url' => $url])
View metric
@endcomponent

Thanks,<br>
{{ config('app.name') }}
@endcomponent
