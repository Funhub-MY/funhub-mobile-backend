<x-mail::message>
# Hello, {{ $user->name }}

You have received a reward from **{{ $source }}**.

**Promo Code 优惠码:** `{{ $promotionCode->code }}`

@if($promotionCode->promotionCodeGroup)
**Reward / 奖励:** {{ $promotionCode->promotionCodeGroup->name }}
@endif

Use this code at checkout in the FUNHUB app to enjoy your reward.<br>
请在 FUNHUB App 结账时使用此优惠码以享受您的奖励。

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
