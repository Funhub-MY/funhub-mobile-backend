<?php

namespace App\Filament\Resources\MerchantOfferVoucherResource\Pages;

use App\Filament\Resources\MerchantOfferVoucherResource;
use App\Models\MerchantOfferCampaign;
use App\Models\MerchantOfferClaim;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ListMerchantOfferVouchers extends ListRecords
{
    protected static string $resource = MerchantOfferVoucherResource::class;

    protected static string $view = 'filament.resources.pages.list-merchant-offer-vouchers';

    /** @var string */
    public $stockSearchCode = '';

    /** @var string Merchant offer name contains… */
    public $stockSearchMerchantOffer = '';

    /** @var string Campaign id or empty (campaigns that have offers with vouchers). */
    public $stockSearchCampaignId = '';

    /** '', 'redeemed', 'not_redeemed' */
    public $stockSearchRedemption = '';

    /** '', 'unclaimed', '1', '2', '3' — matches claim row semantics from Financial Status column. */
    public $stockSearchFinancialStatus = '';

    protected $queryString = [
        'isTableReordering' => ['except' => false],
        'tableFilters',
        'tableSortColumn' => ['except' => ''],
        'tableSortDirection' => ['except' => ''],
        'tableSearchQuery' => ['except' => ''],
        'stockSearchCode' => ['except' => ''],
        'stockSearchMerchantOffer' => ['except' => ''],
        'stockSearchCampaignId' => ['except' => ''],
        'stockSearchRedemption' => ['except' => ''],
        'stockSearchFinancialStatus' => ['except' => ''],
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

    /**
     * Campaigns that have at least one offer with vouchers.
     */
    public function getCampaignOptionsProperty(): array
    {
        return MerchantOfferCampaign::query()
            ->whereHas('merchantOffers', function (Builder $q) {
                $q->whereHas('vouchers');
            })
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    protected function getTableQuery(): Builder
    {
        $query = parent::getTableQuery();

        $canFilterCampaign = auth()->check() && ! auth()->user()->hasRole('merchant');
        if ($canFilterCampaign && filled($this->stockSearchCampaignId)) {
            $query->whereHas('merchant_offer', function (Builder $q) {
                $q->where('merchant_offer_campaign_id', $this->stockSearchCampaignId);
            });
        }

        if (filled($this->stockSearchMerchantOffer)) {
            $like = '%'.$this->escapeLike(trim($this->stockSearchMerchantOffer)).'%';
            $query->whereHas('merchant_offer', function (Builder $q) use ($like) {
                $q->where('merchant_offers.name', 'like', $like);
            });
        }

        if (filled($this->stockSearchCode)) {
            $like = '%'.$this->escapeLike(trim($this->stockSearchCode)).'%';
            $query->where(function (Builder $q) use ($like) {
                $q->where('merchant_offer_vouchers.code', 'like', $like)
                    ->orWhere('merchant_offer_vouchers.imported_code', 'like', $like);
            });
        }

        $financial = (string) ($this->stockSearchFinancialStatus ?? '');
        if ($financial === 'unclaimed') {
            $query->whereNotExists(function ($subquery) {
                $subquery->select(DB::raw(1))
                    ->from('merchant_offer_user')
                    ->whereColumn('merchant_offer_user.voucher_id', 'merchant_offer_vouchers.id')
                    ->where('merchant_offer_user.status', MerchantOfferClaim::CLAIM_SUCCESS)
                    ->limit(1);
            });
        } elseif ($financial !== '' && in_array((int) $financial, [
            MerchantOfferClaim::CLAIM_SUCCESS,
            MerchantOfferClaim::CLAIM_FAILED,
            MerchantOfferClaim::CLAIM_AWAIT_PAYMENT,
        ], true)) {
            $statusVal = (int) $financial;
            $query->whereExists(function ($subquery) use ($statusVal) {
                $subquery->select(DB::raw(1))
                    ->from('merchant_offer_user as mou1')
                    ->whereColumn('mou1.voucher_id', 'merchant_offer_vouchers.id')
                    ->where('mou1.status', $statusVal)
                    ->whereRaw(
                        'mou1.created_at = (
                            SELECT MAX(mou2.created_at)
                            FROM merchant_offer_user as mou2
                            WHERE mou2.voucher_id = mou1.voucher_id
                        )'
                    );
            });
        }

        if ($this->stockSearchRedemption === 'redeemed') {
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
        $this->stockSearchCampaignId = '';
        $this->stockSearchRedemption = '';
        $this->stockSearchFinancialStatus = '';
        $this->resetPage();
    }

    protected function getActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
