<x-mail::message>
# Purchase Receipt

Thank you for your purchase!

**Transaction No:** {{ $transactionNo }} 

**Date and Time of Purchase:** {{ $dateTime }}

**Title of Item Purchase:** {{ $itemTitle }}

**Quantity:** {{ $quantity }}

@if ($currencyType === 'points')
**Subtotal (points):** {{ $subtotal }}
@elseif ($currencyType === 'MYR')
**Subtotal (MYR):** {{ $subtotal }}
@endif

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>