@component('mail::message')
# Review requested: {{ $metricName }}

A change proposal for **{{ $metricName }}** at **{{ $organizationName }}** needs your decision.

@isset($initiator)
Opened by {{ $initiator }}.
@endisset

@if(! empty($rationale))
> {{ $rationale }}
@endif

@component('mail::button', ['url' => $url])
Review proposal
@endcomponent

Thanks,<br>
{{ config('app.name') }}
@endcomponent
