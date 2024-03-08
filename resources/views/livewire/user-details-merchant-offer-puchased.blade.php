<!-- Check if there are any records -->
@if ($offerPuchasedData->isEmpty())
    <div class="py-4 text-center">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-20 w-20 inline-block align-middle" viewBox="0 0 48 48" fill="none">
            <circle cx="24" cy="24" r="20" fill="rgb(255, 251, 235)" />
            <path stroke="rgb(245, 158, 11)" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 34L34 14M14 14l20 20" />
        </svg>
        <div class="no-record-found font-bold leading-7 text-gray-700">No records found</div>
    </div>
@else
    <div class="overflow-x-auto">
        <table class="w-full text-left">
            <thead>
                <tr class="border-b border-black px-4 py-2">
                    <th class="py-2 px-4">Order No.</th>
                    <th class="py-2 px-4">Item Name</th>
                    <th class="py-2 px-4">Net Amount</th>                
                    <th class="py-2 px-4">Unit Price</th>
                    <th class="py-2 px-4">Quantity</th>
                    <th class="py-2 px-4">Discount</th>
                    <th class="py-2 px-4">Tax</th>
                    <th class="py-2 px-4">Total</th>
                    <th class="py-2 px-4">Purchase Method</th>
                    <th class="py-2 px-4">Transaction No.</th>
                    <th class="py-2 px-4">Status</th>
                    <th class="py-2 px-4">Created At</th>
                </tr>
            </thead>

            <tbody>
                <!-- Loop through points and display each record -->
                @foreach ($offerPuchasedData as $data)
                    @php
                        $purchasedData = json_decode($data, true);
                        $createdAt = \Carbon\Carbon::parse($purchasedData['created_at']);
                    @endphp
                    <tr class="border-t border-black">
                        <td class="py-2 px-4">{{ $purchasedData['order_no'] }}</td>
                        <td class="py-2 px-4">{{ $data->merchantOffer->name }}</td>
                        <td class="py-2 px-4">{{ $purchasedData['net_amount'] }}</td>
                        <td class="py-2 px-4">{{ $purchasedData['unit_price'] }}</td>
                        <td class="py-2 px-4">{{ $purchasedData['quantity'] }}</td>
                        <td class="py-2 px-4">{{ $purchasedData['discount'] }}</td>
                        <td class="py-2 px-4">{{ $purchasedData['tax'] }}</td>
                        <td class="py-2 px-4">{{ $purchasedData['total'] }}</td>
                        <td class="py-2 px-4">{{ $purchasedData['purchase_method'] }}</td>
                        <td class="py-2 px-4">{{ $purchasedData['transaction_no'] }}</td>
                        <td class=" py-2 px-4">
                            @php
                                $status = '';
                                $badgeClass = '';
                                switch ($purchasedData['status']) {
                                    case 1:
                                        $status = 'Success';
                                        $badgeClass = 'green-badge';
                                        break;
                                    case 2:
                                        $status = 'Failed';
                                        $badgeClass = 'pink-badge';
                                        break;
                                    case 3:
                                        $status = 'Awaiting Payment';
                                        $badgeClass = 'yellow-badge';
                                        break;
                                    default:
                                        $status = 'Unknown';
                                        break;
                                }
                            @endphp
                            <span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium {{ $badgeClass }}">
                                {{ $status }}
                            </span>
                        </td>
                        <td class="py-2 px-4">{{ $createdAt->format('d/m/Y H:i:s') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

<style>
    .no-record-found {
        font-family: "DM Sans", ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", "Noto Color Emoji";
        font-size: 16px;
        font-weight: 700;
        line-height: 24px;
        color: rgb(17, 24, 39);
    }

    .green-badge {
        background-color: rgb(240 253 244);
        color: rgb(21 128 61);
    }

    .pink-badge {
        background-color: rgb(253 242 248);
        color: rgb(190 24 93);
    }

    .yellow-badge {
        background-color: rgb(254 252 232);
        color: rgb(133 77 14);
    }
</style>