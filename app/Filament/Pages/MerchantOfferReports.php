<?php
namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Pages\Actions\Action;
use Filament\Tables\Columns;
use Filament\Tables\Table;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\DatePicker;
use App\Models\MerchantOfferVoucher;
use App\Models\MerchantOffer;
use App\Models\MerchantOfferClaim;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use NumberFormatter;
use App\Exports\MerchantOfferReportsExport;
use Maatwebsite\Excel\Facades\Excel;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;

class MerchantOfferReports extends Page implements HasTable
{
    use InteractsWithTable, HasPageShield;
    
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 2;


    protected static ?string $title = 'Merchant Offer Reports (BD)';
    protected static ?string $navigationLabel = 'Merchant Offer Reports (BD)';

    protected static ?string $slug = 'merchant-offer-reports';

    protected static string $view = 'filament.pages.merchant-offer-reports';

    protected function getTableRecordsPerPageSelectOptions(): array 
    {
        return [10, 25, 50, 100];
    } 

    protected function getActions(): array
    {
        return [
            Action::make('export')
                ->label('Export to Excel')
                ->icon('heroicon-o-download')
                ->requiresConfirmation()
                ->action(function (array $data) {
                    // Get filters from the table
                    $filters = $this->tableFilters;

                    // Extract specific filters like start_date and end_date
                    $startDate = isset($filters['period']['start_date']) ? Carbon::parse($filters['period']['start_date']) : null;
                    $endDate = isset($filters['period']['end_date']) ? Carbon::parse($filters['period']['end_date']) : null;

                    // Generate the file name
                    $fileName = 'merchant_offer_reports_' .
                        ($startDate ? $startDate->format('Ymd') : 'begin') . '-' .
                        ($endDate ? $endDate->format('Ymd') : date('Ymd')) .
                        '.xlsx';

                    // Pass filters to the export
                    return Excel::download(new MerchantOfferReportsExport($startDate, $endDate), $fileName);
                })
        ];
    }

    protected function getTableColumns(): array
    {
        return [
            Columns\TextColumn::make('id')
                ->label('Offer ID')
                ->sortable(),
            Columns\BadgeColumn::make('offer_status')
                ->enum(MerchantOffer::STATUS)
                ->colors([
                    'secondary' => 0,
                    'success' => 1,
                ])
                ->sortable(),
            Columns\TextColumn::make('offer_name')
                ->label('Offer Name')
                ->sortable()
                ->searchable(isGlobal: true, query: function (Builder $query, string $search): Builder {
                    return $query->where('merchant_offers.name', 'like', "%{$search}%");
                }),
            Columns\TextColumn::make('merchant_name')
                ->label('Merchant Name')
                ->sortable()
                ->searchable(isGlobal: true, query: function (Builder $query, string $search): Builder {
                    return $query->where('merchants.name', 'like', "%{$search}%");
                }),
            Columns\TextColumn::make('brand_name')
                ->label('Brand Name')
                ->sortable()
                ->searchable(isGlobal: true, query: function (Builder $query, string $search): Builder {
                    return $query->where('merchants.brand_name', 'like', "%{$search}%");
                }), 
            Columns\TextColumn::make('total_vouchers')
                ->label('Total Vouchers')
                ->sortable(),
            Columns\TextColumn::make('total_purchases')
                ->label('Total Sold')
                ->sortable(),
            Columns\TextColumn::make('quantity')
                ->label('Offer Remaining Quantity')
                ->sortable(),
            Columns\TextColumn::make('funbox_quantity')
                ->label('Funbox Quantity')
                ->sortable(),
            Columns\TextColumn::make('funbox_original')
                ->label('Funbox Original Price (RM)')
                ->sortable()
                ->formatStateUsing(function (?string $state): string {
                    if ($state === null) {
                        return '-';
                    }

                    // Format the currency
                    $formatter = new NumberFormatter('en_MY', NumberFormatter::CURRENCY);
                    return $formatter->formatCurrency($state, 'MYR');
                }),
            Columns\TextColumn::make('funbox_selling')
                ->label('Funbox Selling Price (RM)')
                ->sortable()
                ->formatStateUsing(function (?string $state): string {
                    if ($state === null) {
                        return '-';
                    }

                    // Format the currency
                    $formatter = new NumberFormatter('en_MY', NumberFormatter::CURRENCY);
                    return $formatter->formatCurrency($state, 'MYR');
                }),
            Columns\TextColumn::make('funbox_discount_rate')
                ->label('Funbox Discount Rate')
                ->sortable()
                ->formatStateUsing(function (?float $state): string {
                    return $state !== null ? number_format($state, 2) . '%' : '-';
                }),
            Columns\TextColumn::make('price_original')
                ->label('Original Price (RM)')
                ->sortable()
                ->formatStateUsing(function (?string $state): string {
                    if ($state === null) {
                        return '-';
                    }

                    // Format the currency
                    $formatter = new NumberFormatter('en_MY', NumberFormatter::CURRENCY);
                    return $formatter->formatCurrency($state, 'MYR');
                }),
            Columns\TextColumn::make('price_selling')
                ->label('Selling Price (RM)')
                ->sortable()
                ->formatStateUsing(function (?string $state): string {
                    if ($state === null) {
                        return '-';
                    }

                    // Format the currency
                    $formatter = new NumberFormatter('en_MY', NumberFormatter::CURRENCY);
                    return $formatter->formatCurrency($state, 'MYR');
                }),
            Columns\TextColumn::make('discount_rate')
                ->label('Discount Rate')
                ->sortable()
                ->formatStateUsing(function (?float $state): string {
                    return $state !== null ? number_format($state, 2) . '%' : '-';
                }),
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
        ];
    }

    protected function getTableQuery(): Builder
    {
        $query = MerchantOffer::query()
            ->selectRaw('
                merchant_offers.id AS id,
                merchant_offers.name AS offer_name,
                merchant_offers.quantity AS quantity,
                merchants.name AS merchant_name,
                merchants.brand_name AS brand_name,
                merchant_offers.available_at AS offer_available_at,
                merchant_offers.available_until AS offer_available_until,
                merchant_offers.unit_price AS funbox_quantity,
                merchant_offers.point_fiat_price AS funbox_original,
                merchant_offers.discounted_point_fiat_price AS funbox_selling,
                merchant_offers.fiat_price AS price_original,
                merchant_offers.discounted_fiat_price AS price_selling,
                CASE 
                    WHEN merchant_offers.point_fiat_price > 0 THEN 
                        ((1 - (merchant_offers.discounted_point_fiat_price / merchant_offers.point_fiat_price)) * 100)
                    ELSE 0
                END AS funbox_discount_rate,
                CASE 
                    WHEN merchant_offers.fiat_price > 0 THEN 
                        ((1 - (merchant_offers.discounted_fiat_price / merchant_offers.fiat_price)) * 100)
                    ELSE 0
                END AS discount_rate,
                merchant_offers.status AS offer_status,
                (SELECT COUNT(*) 
                    FROM merchant_offer_vouchers 
                    WHERE merchant_offer_vouchers.merchant_offer_id = merchant_offers.id
                ) AS total_vouchers,
                (SELECT COUNT(*) 
                    FROM merchant_offer_user 
                    WHERE merchant_offer_user.merchant_offer_id = merchant_offers.id 
                    AND merchant_offer_user.status = ?
                ) AS total_purchases
            ', [MerchantOfferClaim::CLAIM_SUCCESS])
            ->leftJoin('merchants', 'merchants.user_id', '=', 'merchant_offers.user_id')
            ->groupBy([
                'merchant_offers.id',
                'merchant_offers.name',
                'merchant_offers.quantity',
                'merchant_offers.status',
                'merchants.name',
                'merchants.brand_name',
                'merchant_offers.available_at',
                'merchant_offers.available_until',
                'merchant_offers.unit_price',
                'merchant_offers.point_fiat_price',
                'merchant_offers.discounted_point_fiat_price',
                'merchant_offers.fiat_price',
                'merchant_offers.discounted_fiat_price'
            ])
            ->when(
                $this->getTableSortColumn(), 
                fn (Builder $query): Builder => $query->orderBy(
                    $this->getTableSortColumn(), 
                    $this->getTableSortDirection() ?? 'asc'
                )
            )
            ->orderBy('merchant_offers.id', 'ASC')
            ->orderBy('merchant_offers.name', 'DESC')
            ->orderBy('merchant_offers.created_at', 'ASC');


        // Get filters from the table
        $filters = $this->tableFilters;


        // Extract specific filters like start_date and end_date
        $startDate  = isset($filters['period']['start_date']) ? Carbon::parse($filters['period']['start_date']) : null;
        $endDate    = isset($filters['period']['end_date']) ? Carbon::parse($filters['period']['end_date']) : null;

        // Apply date filters if provided
        if ($startDate) {
            $query->whereDate('merchant_offer_user.created_at', '>=', Carbon::parse($startDate)->format('Y-m-d'));
        }

        if ($endDate) {
            $query->whereDate('merchant_offer_user.created_at', '<=', Carbon::parse($endDate)->format('Y-m-d'));
        }

        return $query;
        // $query = MerchantOfferVoucher::query()
        //     ->selectRaw('
        //         merchant_offer_vouchers.merchant_offer_id AS id,
        //         merchant_offers.name AS offer_name,
        //         merchant_offers.quantity as quantity,
        //         merchants.name AS merchant_name,
        //         merchants.brand_name AS brand_name,
        //         merchant_offers.available_at AS offer_available_at,
        //         merchant_offers.available_until AS offer_available_until,
        //         COUNT(merchant_offer_vouchers.id) AS total_purchases,
        //         merchant_offers.unit_price as funbox_quantity,
        //         merchant_offers.point_fiat_price as funbox_original,
        //         merchant_offers.discounted_point_fiat_price as funbox_selling,
        //         merchant_offers.fiat_price as price_original,
        //         merchant_offers.discounted_fiat_price as price_selling,
        //         CASE 
        //             WHEN merchant_offers.point_fiat_price > 0 THEN 
        //                 ((1 - (merchant_offers.discounted_point_fiat_price / merchant_offers.point_fiat_price)) * 100)
        //             ELSE 0
        //         END AS funbox_discount_rate,
        //         CASE 
        //             WHEN merchant_offers.fiat_price > 0 THEN 
        //                 ((1 - (merchant_offers.discounted_fiat_price / merchant_offers.fiat_price)) * 100)
        //             ELSE 0
        //         END AS discount_rate
        //     ')
        //     ->join('merchant_offers', 'merchant_offers.id', '=', 'merchant_offer_vouchers.merchant_offer_id')
        //     ->leftJoin('merchants', 'merchants.user_id', '=', 'merchant_offers.user_id')
        //     ->join('merchant_offer_user', function ($join) {
        //         $join->on('merchant_offer_user.voucher_id', '=', 'merchant_offer_vouchers.id')
        //             ->whereColumn('merchant_offer_user.user_id', '=', 'merchant_offer_vouchers.owned_by_id')
        //             ->where('merchant_offer_user.status', '=', MerchantOfferClaim::CLAIM_SUCCESS);
        //     })
        //     ->whereNotNull('merchant_offer_vouchers.owned_by_id')
        //     ->groupBy([
        //         'merchant_offer_vouchers.merchant_offer_id',
        //         'merchant_offers.name',
        //         'merchant_offers.quantity',
        //         'merchants.name',
        //         'merchants.brand_name',
        //         'merchant_offers.available_at',
        //         'merchant_offers.available_until',
        //         'merchant_offers.unit_price',
        //         'merchant_offers.point_fiat_price',
        //         'merchant_offers.discounted_point_fiat_price',
        //         'merchant_offers.fiat_price',
        //         'merchant_offers.discounted_fiat_price'
        //     ])
        //     ->when(
        //         $this->getTableSortColumn(), 
        //         fn (Builder $query): Builder => $query->orderBy(
        //             $this->getTableSortColumn(), 
        //             $this->getTableSortDirection() ?? 'asc'
        //         )
        //     )
        //     ->orderBy('merchant_offers.id', 'ASC')
        //     ->orderBy('merchant_offers.name', 'DESC')
        //     ->orderBy('merchant_offers.created_at', 'ASC');

        // // Get filters from the table
        // $filters = $this->tableFilters;


        // // Extract specific filters like start_date and end_date
        // $startDate  = isset($filters['period']['start_date']) ? Carbon::parse($filters['period']['start_date']) : null;
        // $endDate    = isset($filters['period']['end_date']) ? Carbon::parse($filters['period']['end_date']) : null;

        // // Apply date filters if provided
        // if ($startDate) {
        //     $query->whereDate('merchant_offer_user.created_at', '>=', Carbon::parse($startDate)->format('Y-m-d'));
        // }

        // if ($endDate) {
        //     $query->whereDate('merchant_offer_user.created_at', '<=', Carbon::parse($endDate)->format('Y-m-d'));
        // }

        // return $query;
    }
}