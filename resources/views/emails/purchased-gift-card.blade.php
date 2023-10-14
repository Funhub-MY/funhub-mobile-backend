<x-mail::message>
# Purchase Receipt

Thank you for your purchase!

**Transaction No:** {{ $transactionNo }}

**Date and Time of Purchase:** {{ $dateTime }}

**Title of Item Purchase:** {{ $itemTitle }}

**Quantity:** {{ $quantity }}

**Subtotal (MYR):** {{ $subtotal }}

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>