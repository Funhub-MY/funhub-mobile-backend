<x-mail::message>
Dear Admin,

### {{ $content }}<br>

Category: {{ $category }}<br>
Title: {{ $title }}<br>

<x-mail::button :url="url('/')">
Click here to check request
</x-mail::button>

Have a nice day!<br>
{{ config('app.name') }}
</x-mail::message>