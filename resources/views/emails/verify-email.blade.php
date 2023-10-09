<x-mail::message>
# Hello, {{ $username }}

Please enter the following code to the App to verify your email address.
请在应APP输入以下代码以验证您的电子邮件地址。

# {{ $token }}

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
