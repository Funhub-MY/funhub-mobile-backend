<x-mail::message>
Welcome {{ $merchantName }},

Below are your login details:<br>
Email: {{ $userEmail }}<br>
Password: {{ $defaultPassword }}

<x-mail::button :url="'/'">
Click here to Login
</x-mail::button>

Steps:

Thank you,<br>
{{ config('app.name') }}
</x-mail::message>