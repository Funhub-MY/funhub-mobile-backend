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
            {{ __('Fill any field and click Search. Redeem date uses the redemption record timestamp. Do not combine “Not redeemed” with a redeem date range.') }}
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
                    {{ __('Merchant offer') }}
                </label>
                <input
                    type="text"
                    wire:model.defer="stockSearchMerchantOffer"
                    class="filament-forms-input block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-600 focus:ring-primary-600 dark:border-gray-600 dark:bg-gray-700 dark:text-white sm:text-sm"
                    placeholder="{{ __('Offer name contains…') }}"
                    autocomplete="off"
                />
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                    {{ __('SKU') }}
                </label>
                <input
                    type="text"
                    wire:model.defer="stockSearchSku"
                    class="filament-forms-input block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-600 focus:ring-primary-600 dark:border-gray-600 dark:bg-gray-700 dark:text-white sm:text-sm"
                    placeholder="{{ __('SKU contains…') }}"
                    autocomplete="off"
                />
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

            <div class="md:col-span-2">
                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                    {{ __('Purchased by user') }}
                </label>
                <input
                    type="text"
                    wire:model.defer="stockSearchPurchasedBy"
                    class="filament-forms-input block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-600 focus:ring-primary-600 dark:border-gray-600 dark:bg-gray-700 dark:text-white sm:text-sm"
                    placeholder="{{ __('Buyer name contains…') }}"
                    autocomplete="off"
                />
            </div>

            <div class="border-t border-gray-200 pt-4 dark:border-gray-600 md:col-span-2 lg:col-span-3">
                <p class="mb-2 text-sm font-medium text-gray-800 dark:text-gray-200">
                    {{ __('Redeem date range') }}
                </p>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ __('From') }}
                        </label>
                        <input
                            type="date"
                            wire:model.defer="stockSearchRedeemDateFrom"
                            class="filament-forms-input block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-600 focus:ring-primary-600 dark:border-gray-600 dark:bg-gray-700 dark:text-white sm:text-sm"
                        />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ __('Until') }}
                        </label>
                        <input
                            type="date"
                            wire:model.defer="stockSearchRedeemDateUntil"
                            class="filament-forms-input block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-600 focus:ring-primary-600 dark:border-gray-600 dark:bg-gray-700 dark:text-white sm:text-sm"
                        />
                    </div>
                </div>
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
