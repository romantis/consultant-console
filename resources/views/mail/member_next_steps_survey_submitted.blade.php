<x-mail::message>
# Member next-step survey

A member shared comprehension feedback from the next-step view.

<x-mail::panel>
**Organization:** {{ optional($survey->organization)->name ?? $survey->organization_id }}

**Engagement:** {{ optional($survey->engagement)->title ?? $survey->engagement_id }}

**Membership:** {{ optional($survey->membership)->id ?? $survey->membership_id }}

**Clarity score:** {{ $survey->clarity_score }} / 5

**Understood the task?** {{ $survey->understood ? 'Yes' : 'No' }}

**Contact OK?** {{ $survey->contact_ok ? 'Yes' : 'No' }}

**Source:** {{ ucfirst($survey->source) }}
</x-mail::panel>

@if ($survey->comment)
**Comment**

> {{ $survey->comment }}
@endif

@if (!empty($survey->metadata))
**Metadata**

```json
{!! json_encode($survey->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) !!}
```
@endif

<x-mail::button :url="config('app.url')">
View Console
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
