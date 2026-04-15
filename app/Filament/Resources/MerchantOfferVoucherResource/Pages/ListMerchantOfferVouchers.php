<?php

namespace App\Filament\Resources\MerchantOfferVoucherResource\Pages;

use App\Filament\Resources\MerchantOfferVoucherResource;
use Filament\Pages\Actions;
use Carbon\Carbon;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ListMerchantOfferVouchers extends ListRecords
{
    protected static string $resource = MerchantOfferVoucherResource::class;

    protected static string $view = 'filament.resources.pages.list-merchant-offer-vouchers';

    /** @var string */
    public $stockSearchCode = '';

    /** @var string */
    public $stockSearchMerchantOffer = '';

    /** @var string */
    public $stockSearchSku = '';

    /** '', 'redeemed', 'not_redeemed' */
    public $stockSearchRedemption = '';

    /** @var string */
    public $stockSearchPurchasedBy = '';

    /** @var string Redeem date range (HTML date input Y-m-d); filters `merchant_offer_claims_redemptions.created_at`. */
    public $stockSearchRedeemDateFrom = '';

    /** @var string */
    public $stockSearchRedeemDateUntil = '';

    protected $queryString = [
        'isTableReordering' => ['except' => false],
        'tableFilters',
        'tableSortColumn' => ['except' => ''],
        'tableSortDirection' => ['except' => ''],
        'tableSearchQuery' => ['except' => ''],
        'stockSearchCode' => ['except' => ''],
        'stockSearchMerchantOffer' => ['except' => ''],
        'stockSearchSku' => ['except' => ''],
        'stockSearchRedemption' => ['except' => ''],
        'stockSearchPurchasedBy' => ['except' => ''],
        'stockSearchRedeemDateFrom' => ['except' => ''],
        'stockSearchRedeemDateUntil' => ['except' => ''],
    ];

    /**
     * Default Filament pagination includes -1 ("View all"); keep list bounded.
     */
    protected int $defaultTableRecordsPerPageSelectOption = 10;

    protected function getTableRecordsPerPageSelectOptions(): array
    {
        return [10, 25, 50, 100];
    }

    protected function getTableFilters(): array
    {
        return [];
    }

    protected function getTableQuery(): Builder
    {
        $query = parent::getTableQuery();

        if (filled($this->stockSearchCode)) {
            $like = '%'.$this->escapeLike(trim($this->stockSearchCode)).'%';
            $query->where(function (Builder $q) use ($like) {
                $q->where('merchant_offer_vouchers.code', 'like', $like)
                    ->orWhere('merchant_offer_vouchers.imported_code', 'like', $like);
            });
        }

        if (filled($this->stockSearchMerchantOffer)) {
            $like = '%'.$this->escapeLike(trim($this->stockSearchMerchantOffer)).'%';
            $query->whereHas('merchant_offer', function (Builder $q) use ($like) {
                $q->where('merchant_offers.name', 'like', $like);
            });
        }

        if (filled($this->stockSearchSku)) {
            $like = '%'.$this->escapeLike(trim($this->stockSearchSku)).'%';
            $query->whereHas('merchant_offer', function (Builder $q) use ($like) {
                $q->where('merchant_offers.sku', 'like', $like);
            });
        }

        $redeemFrom = filled($this->stockSearchRedeemDateFrom ?? null)
            ? (string) $this->stockSearchRedeemDateFrom
            : null;
        $redeemUntil = filled($this->stockSearchRedeemDateUntil ?? null)
            ? (string) $this->stockSearchRedeemDateUntil
            : null;
        $hasRedeemDateRange = filled($redeemFrom) || filled($redeemUntil);

        if ($hasRedeemDateRange && $this->stockSearchRedemption === 'not_redeemed') {
            // Cannot be "not redeemed" and have a redemption in a date range.
            $query->whereRaw('1 = 0');
        } elseif ($hasRedeemDateRange) {
            $query->whereExists(function ($subquery) use ($redeemFrom, $redeemUntil) {
                $subquery->select(DB::raw(1))
                    ->from('merchant_offer_claims_redemptions as mor')
                    ->join('merchant_offer_user as mou', 'mor.claim_id', '=', 'mou.id')
                    ->whereColumn('mou.voucher_id', 'merchant_offer_vouchers.id');

                if (filled($redeemFrom)) {
                    $subquery->where('mor.created_at', '>=', Carbon::parse($redeemFrom)->startOfDay());
                }
                if (filled($redeemUntil)) {
                    $subquery->where('mor.created_at', '<=', Carbon::parse($redeemUntil)->endOfDay());
                }
            });
        } elseif ($this->stockSearchRedemption === 'redeemed') {
            $query->whereExists(function ($subquery) {
                $subquery->select(DB::raw(1))
                    ->from('merchant_offer_claims_redemptions as mor')
                    ->join('merchant_offer_user as mou', 'mor.claim_id', '=', 'mou.id')
                    ->whereColumn('mou.voucher_id', 'merchant_offer_vouchers.id');
            });
        } elseif ($this->stockSearchRedemption === 'not_redeemed') {
            $query->whereNotExists(function ($subquery) {
                $subquery->select(DB::raw(1))
                    ->from('merchant_offer_claims_redemptions as mor')
                    ->join('merchant_offer_user as mou', 'mor.claim_id', '=', 'mou.id')
                    ->whereColumn('mou.voucher_id', 'merchant_offer_vouchers.id');
            });
        }

        if (filled($this->stockSearchPurchasedBy)) {
            $like = '%'.$this->escapeLike(trim($this->stockSearchPurchasedBy)).'%';
            $query->whereHas('owner', function (Builder $q) use ($like) {
                $q->where('users.name', 'like', $like);
            });
        }

        return $query;
    }

    protected function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    public function applyStockVoucherSearch(): void
    {
        $this->resetPage();
    }

    public function resetStockVoucherSearch(): void
    {
        $this->stockSearchCode = '';
        $this->stockSearchMerchantOffer = '';
        $this->stockSearchSku = '';
        $this->stockSearchRedemption = '';
        $this->stockSearchPurchasedBy = '';
        $this->stockSearchRedeemDateFrom = '';
        $this->stockSearchRedeemDateUntil = '';
        $this->resetPage();
    }

    protected function getActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
