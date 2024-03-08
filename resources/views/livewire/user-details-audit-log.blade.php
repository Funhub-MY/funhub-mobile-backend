<!-- Check if there are any records -->
@if ($auditData->isEmpty())
    <div class="py-4 text-center">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-20 w-20 inline-block align-middle" viewBox="0 0 48 48" fill="none">
            <circle cx="24" cy="24" r="20" fill="rgb(255, 251, 235)" />
            <path stroke="rgb(245, 158, 11)" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 34L34 14M14 14l20 20"/>
        </svg>
        <div class="no-record-found font-bold leading-7 text-gray-700">No records found</div>
    </div>
@else
    <div class="overflow-x-auto">
        <table class="w-full text-left">
            <thead>
                <tr class="border-b border-black px-4 py-2">
                    <th class="py-2 px-4">Event</th>
                    <th class="py-2 px-4">Created At</th>
                    <th class="py-2 px-4">Old Values</th>
                    <th class="py-2 px-4">New Values</th>
                </tr>
            </thead>

            <tbody>
                <!-- Loop through audits and display each record -->
                @foreach ($auditData as $audit)
                    @php
                        // Get the name corresponding to each old and new value
                        $oldValuesWithNames = collect($audit->old_values)->map(function ($value, $key) {
                            return "<span class='badge inline-flex items-center rounded-md px-1 py-1 text-xs font-medium'>$key</span> $value";
                        });
                        $newValuesWithNames = collect($audit->new_values)->map(function ($value, $key) {
                            return "<span class='badge inline-flex items-center rounded-md px-1 py-1 text-xs font-medium'>$key</span> $value";
                        });
                        $createdAt = \Carbon\Carbon::parse($audit->created_at);
                    @endphp
                        <tr class="border-t border-black">
                            <td class="py-2 px-4">{{ $audit->event }}</td>
                            <td class="py-2 px-4">{{ $createdAt->format('d/m/Y H:i:s') }}</td>
                            <td class="py-2 px-4">
                                <!-- Display old values with names -->
                                @foreach ($oldValuesWithNames as $value)
                                    {!! $value !!} 
                                @endforeach
                            </td>
                            <td class="py-2 px-4">
                                <!-- Display new values with names -->
                                @foreach ($newValuesWithNames as $value)
                                    {!! $value !!}
                                @endforeach
                            </td>
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

    .badge {
        background-color: rgb(240,240,242);
        color: rgb(55,65,81);
    }
</style>