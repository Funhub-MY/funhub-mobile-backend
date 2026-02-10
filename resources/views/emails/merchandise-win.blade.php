<x-mail::message>
# Hello, {{ $user->name }}

Congratulations! You have won a **physical product** from **{{ $source }}**.

**Prize / 奖品:** {{ $merchandise->name }}

Please **contact Admin** for details of redemption (e.g. collection or delivery).<br>
请**联系管理员**以获取兑换详情（如领取或配送方式）。

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
