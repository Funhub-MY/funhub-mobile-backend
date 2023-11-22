<x-mail::message>
Dear Admin,

### Kindly note that a new support request has been raised<br>

Category: {{ $category }}<br>
Title: {{ $title }}<br>

<x-mail::button :url="url('/')">
Click here to check request
</x-mail::button>

Have a nice day!<br>
{{ config('app.name') }}
</x-mail::message>