<div>
    <x-filament::tabs>
        <x-filament::tabs.item :active="$activeTab === '1'" wire:click="$set('activeTab', '1')">
            User Details
        </x-filament::tabs.item>

        <x-filament::tabs.item :active="$activeTab === '2'" wire:click="$set('activeTab', '2')">
            Point Ledgers
        </x-filament::tabs.item>

        <x-filament::tabs.item :active="$activeTab === '3'" wire:click="$set('activeTab', '3')">
            Merchant Offer Purchased
        </x-filament::tabs.item>

        <x-filament::tabs.item :active="$activeTab === '4'" wire:click="$set('activeTab', '4')">
            Transaction History
        </x-filament::tabs.item>

        <x-filament::tabs.item :active="$activeTab === '5'" wire:click="$set('activeTab', '5')">
            Audit Log
        </x-filament::tabs.item>
    </x-filament::tabs>

    @switch($activeTab)
        @case("1")
            <div class="mt-4">
                @livewire('user-details-table', ['currentRouteId' => $currentRouteId])
            </div>
            @break
        @case("2")
            <div class="mt-4">
                @livewire('point-ledger-table', ['currentRouteId' => $currentRouteId])
            </div>
            @break
        @case("3")
            <div class="mt-4">
                @livewire('merchant-offer-purchased-table', ['currentRouteId' => $currentRouteId])
            </div>
            @break
        @case("4")
            <div class="mt-4">
                @livewire('transaction-history-table', ['currentRouteId' => $currentRouteId])
            </div>
            @break
        @case("5")
            <div class="mt-4">
                @livewire('audit-log-table', ['currentRouteId' => $currentRouteId])
            </div>
            @break
    @endswitch
</div>