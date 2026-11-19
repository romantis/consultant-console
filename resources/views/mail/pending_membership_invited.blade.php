@component('mail::message')
# You've been invited!

You have been invited to join **{{ $organizationName ?? 'an organization' }}** on the MH Console.

@if(! empty($roleLabel))
You will join with the **{{ $roleLabel }}** role.
@endif

@if(! empty($inviterName))
This invitation was sent by **{{ $inviterName }}**.
@endif

To accept, sign in with this email or create an account first, then confirm access.

Need an account? Register now at <a href="{{ $registerUrl }}">{{ $registerUrl }}</a>.

@component('mail::button', ['url' => $acceptUrl])
Accept invitation
@endcomponent

This secure link expires on {{ $expiresAt->toDayDateTimeString() }}.

If you did not expect this invitation, you can safely ignore this email.

Thanks,<br>
{{ $appName ?? config('app.name') }}
@endcomponent
