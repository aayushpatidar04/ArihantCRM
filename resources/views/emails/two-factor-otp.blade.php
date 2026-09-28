@component('mail::message')
# Your verification code

Hello {{ $name }},

Use the following 6-digit code to complete your sign-in. It expires in 10 minutes.

@component('mail::panel')
**{{ $code }}**
@endcomponent

If you did not request this code, you can safely ignore this email.

Thanks,
{{ config('app.name') }}
@endcomponent