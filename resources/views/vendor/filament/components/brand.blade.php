@if(str_contains(request()->url(), 'login'))
<img src="{{ asset('/images/funhub-logo-new.png') }}" alt="FUNHUB" style="height: 100px">
@else
<img src="{{ asset('/images/funhub-logo-text.png') }}" alt="FUNHUB" style="height: 50px">
@endif
