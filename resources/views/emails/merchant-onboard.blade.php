<x-mail::message>
Welcome {{ $merchantName }},

### Below are your login details<br>

Email: {{ $userEmail }}<br>
Password: {{ $defaultPassword }}<br>
Master Code(for Redemption use): {{ $redeemCode }}

<x-mail::button :url="url('/')">
Click here to Login
</x-mail::button>

Thank you,<br>
{{ config('app.name') }}
</x-mail::message>
