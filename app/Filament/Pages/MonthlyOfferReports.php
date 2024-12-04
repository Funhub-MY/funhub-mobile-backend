<?php
namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Tables\Columns;
use Filament\Tables\Table;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\DatePicker;
use App\Models\MerchantOfferVoucher;
use App\Models\MerchantOffer;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;

class MonthlyOfferReports extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.monthly-offer-reports';

    protected function getTableColumns(): array
    {
        return [
            Columns\TextColumn::make('id')
                ->label('Offer ID'),
            Columns\TextColumn::make('offer_name')
                ->label('Offer Name')
                ->searchable(isGlobal: true, query: function (Builder $query, string $search): Builder {
                    return $query->where('merchant_offers.name', 'like', "%{$search}%");
                }),
            Columns\TextColumn::make('store_name')
                ->label('Store Name')
                ->searchable(isGlobal: true, query: function (Builder $query, string $search): Builder {
                    return $query->where('stores.name', 'like', "%{$search}%");
                }),
            Columns\TextColumn::make('total_purchases')
                ->label('Total Purchases')
                ->sortable(),
            Columns\TextColumn::make('offer_available_at')
                ->label('Available From')
                ->dateTime()
                ->sortable(),
            Columns\TextColumn::make('offer_available_until')
                ->label('Available Until')
                ->dateTime()
                ->sortable(),
            
        ];
    }

    protected function getTableFilters(): array
    {

        return [ 
            Filter::make('period')
                ->form([
                    DatePicker::make('start_date')->required(),
                    DatePicker::make('end_date')->required(),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    return $query
                        ->when(
                            $data['start_date'],
                            fn (Builder $query, $date): Builder => $query->where('merchant_offer_claims_redemptions.created_at', '>=', $date)
                        )
                        ->when(
                            $data['end_date'],
                            fn (Builder $query, $date): Builder => $query->where('merchant_offer_claims_redemptions.created_at', '<=', $date)
                        );
                })
        ];
    }

    protected function getTableQuery(): Builder
    {
        return MerchantOfferVoucher::query()
            ->selectRaw('
                merchant_offer_vouchers.merchant_offer_id AS id,
                merchant_offers.name AS offer_name,
                merchant_offers.store_id AS store_id,
                stores.name AS store_name,
                merchant_offers.available_at AS offer_available_at,
                merchant_offers.available_until AS offer_available_until,
                COUNT(merchant_offer_user.id) AS total_purchases
            ')
            ->join('merchant_offers', 'merchant_offers.id', '=', 'merchant_offer_vouchers.merchant_offer_id')
            ->leftJoin('stores', 'stores.id', '=', 'merchant_offers.store_id')
            ->leftJoin('merchant_offer_user', 'merchant_offer_user.voucher_id', '=', 'merchant_offer_vouchers.id')
            ->leftJoin('merchant_offer_claims_redemptions', 'merchant_offer_claims_redemptions.claim_id', '=', 'merchant_offer_user.id')
            ->whereNotNull('merchant_offer_vouchers.owned_by_id')
            ->groupBy([
                'merchant_offer_vouchers.merchant_offer_id',
                'merchant_offers.name',
                'merchant_offers.store_id',
                'stores.name',
                'merchant_offers.available_at',
                'merchant_offers.available_until',
            ])
            ->orderBy('merchant_offers.name', 'DESC');
    }
}