<x-mail::message>
Dear {{ $merchantName }} 您好,

@if ($userEmail !== null)
### {{ $username }} ({{ $userEmail }}) has redeemed your voucher:<br>
{{ $username }}（{{ $userEmail }}）已兑换了您的优惠券：<br>
{{ $merchantOffer }}<br>
@else
### {{ $username }} has redeemed your voucher: <br>
{{ $username }} 已兑换了您的优惠券：<br>
{{ $merchantOffer }}<br>
@endif

<x-mail::button :url="'https://merchant.funhub.my'">
Click here to check redemption.
点击这里查看兑换情况。
</x-mail::button>

Have a nice day! 
祝你有美好的一天！<br>
{{ config('app.name') }}
</x-mail::message>
