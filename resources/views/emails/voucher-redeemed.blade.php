<x-mail::message>
Dear {{ $merchantName }} 您好,

### {{ $username }} has redeemed your voucher: {{ $merchantOffer }}.
{{ $username }} 已兑换了您的优惠券 {{ $merchantOffer }}。<br>

<x-mail::button :url="url('/')">
Click here to check redemption.
点击这里查看兑换情况。
</x-mail::button>

Have a nice day! 祝你有美好的一天！<br>
{{ config('app.name') }}
</x-mail::message>
