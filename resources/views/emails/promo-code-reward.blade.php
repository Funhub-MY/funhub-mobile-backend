<x-mail::message>
<img src="{{ asset('images/cny_campaign/form-banner.png') }}" alt="马上FUN好运" style="max-width:100%;height:auto;display:block;margin-bottom:1em;" />

亲爱的 {{ $user->name }}，

感谢您参与【马上FUN好运】活动！
恭喜你成功获得：{{ optional($promotionCode->promotionCodeGroup)->name ?? '优惠码奖励' }}！

**您的优惠码 Promo Code：** `{{ $promotionCode->code }}`

---

## 领取方式

**Promo Code 奖品：**  
1粒 FUNBOX 可直接前往【饭盒积分页面】右上角领取奖励，并输入优惠码领取。

**Promo Code 折扣使用说明：**  
- RM5 OFF：适用于购买 RM35 及以上的 FUNCARD  
- RM10 OFF：适用于购买 RM50 及以上的 FUNCARD  

**实体奖品：**  
请在7天内通过 WhatsApp 联系小饭领取奖品：  
📱 https://wa.me/60127055117  

<img src="{{ asset('images/cny_campaign/whatsapp_qr.png') }}" alt="WhatsApp QR" style="max-width:200px;height:auto;display:block;margin:0.5em 0;" />

小饭会为您核实信息，并说明如何领取奖品。

---

## 注意事项

- 请务必在7天内联系小饭，否则视为放弃领奖资格。
- 奖品不可转让或兑换现金。
- 具体奖品可能适用其他条款，请以实际情况和活动规则为准。

---

Any questions? Get in touch with us!  
Help center: admin@funhub.my

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
