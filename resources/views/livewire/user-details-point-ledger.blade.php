 <!-- Check if there are any records -->
 @if ($pointLedgerData->isEmpty())
    <div class="py-4 text-center">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-20 w-20 inline-block align-middle" viewBox="0 0 48 48" fill="none">
            <circle cx="24" cy="24" r="20" fill="rgb(255, 251, 235)" />
            <path stroke="rgb(245, 158, 11)" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 34L34 14M14 14l20 20"/>
        </svg>
        <div class="no-record-found font-bold leading-7 text-gray-700">No records found</div>
    </div>
@else
    <div class="overflow-x-auto">
    <!-- x-data="{ points: @entangle($getStatePath()) }" -->
        <table class="w-full text-left">
            <thead>
                <tr class="border-b border-black px-4 py-2">
                    <th class="py-2 px-4">Title</th>
                    <th class="py-2 px-4">Item Name</th> 
                    <th class="py-2 px-4">Amount</th>
                    <th class="py-2 px-4">Balance</th>
                    <th class="py-2 px-4">Created At</th>
                </tr>
            </thead>

            <tbody>
                <!-- Loop through points and display each record -->
                @foreach ($pointLedgerData as $point)
                    @php
                        $pointData = json_decode($point, true);
                        $createdAt = \Carbon\Carbon::parse($pointData['created_at']);
                    @endphp
                    <tr class="border-t border-black">
                        <td class="py-2 px-4">{{ $pointData['title'] }}</td>
                        <td class="py-2 px-4">{{ $point->pointable->name }}</td>
                        <td class="py-2 px-4">{{ $pointData['amount'] }}</td>
                        <td class="py-2 px-4">{{ $pointData['balance'] }}</td>
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
</style>