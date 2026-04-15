<x-filament::page
    :class="
        \Illuminate\Support\Arr::toCssClasses([
            'filament-resources-list-records-page',
            'filament-resources-' . str_replace('/', '-', $this->getResource()::getSlug()),
        ])
    "
>
    <div
        wire:key="stock-voucher-search-panel"
        class="mb-6 rounded-lg border border-gray-300 bg-white p-4 shadow-sm dark:border-gray-600 dark:bg-gray-800"
    >
        <h2 class="mb-3 text-lg font-medium text-gray-900 dark:text-white">
            {{ __('Search stock vouchers') }}
        </h2>
        <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">
            {{ __('Fill any field and click Search.') }}
        </p>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                    {{ __('Voucher code') }}
                </label>
                <input
                    type="text"
                    wire:model.defer="stockSearchCode"
                    class="filament-forms-input block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-600 focus:ring-primary-600 dark:border-gray-600 dark:bg-gray-700 dark:text-white sm:text-sm"
                    placeholder="{{ __('Code or imported code') }}"
                    autocomplete="off"
                />
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                    {{ __('Financial status') }}
                </label>
                <select
                    wire:model.defer="stockSearchFinancialStatus"
                    class="filament-forms-input block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-600 focus:ring-primary-600 dark:border-gray-600 dark:bg-gray-700 dark:text-white sm:text-sm"
                >
                    <option value="">{{ __('All') }}</option>
                    <option value="unclaimed">{{ __('Unclaimed') }}</option>
                    @foreach(\App\Models\MerchantOfferClaim::CLAIM_STATUS as $statusId => $statusLabel)
                        <option value="{{ $statusId }}">{{ $statusLabel }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                    {{ __('Redemption status') }}
                </label>
                <select
                    wire:model.defer="stockSearchRedemption"
                    class="filament-forms-input block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-600 focus:ring-primary-600 dark:border-gray-600 dark:bg-gray-700 dark:text-white sm:text-sm"
                >
                    <option value="">{{ __('All') }}</option>
                    <option value="redeemed">{{ __('Redeemed') }}</option>
                    <option value="not_redeemed">{{ __('Not redeemed') }}</option>
                </select>
            </div>
        </div>

        <div class="mt-4 flex flex-wrap gap-2">
            <x-filament::button type="button" wire:click="applyStockVoucherSearch" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="applyStockVoucherSearch">{{ __('Search') }}</span>
                <span wire:loading wire:target="applyStockVoucherSearch">{{ __('Searching…') }}</span>
            </x-filament::button>
            <x-filament::button type="button" color="secondary" wire:click="resetStockVoucherSearch" wire:loading.attr="disabled">
                {{ __('Reset') }}
            </x-filament::button>
        </div>
    </div>

    {{ \Filament\Facades\Filament::renderHook('resource.pages.list-records.table.start') }}

    {{ $this->table }}

    {{ \Filament\Facades\Filament::renderHook('resource.pages.list-records.table.end') }}
</x-filament::page>
