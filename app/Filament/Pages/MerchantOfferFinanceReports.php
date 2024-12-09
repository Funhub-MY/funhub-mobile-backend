<?php
namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Pages\Actions\Action;
use Filament\Tables\Columns;
use Filament\Tables\Table;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Forms\Components\DatePicker;
use App\Models\MerchantOfferVoucher;
use App\Models\MerchantOffer;
use App\Models\MerchantOfferClaim;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use NumberFormatter;
use App\Exports\MerchantOfferFinanceReportsExport;
use Maatwebsite\Excel\Facades\Excel;

class MerchantOfferFinanceReports extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 3;


    protected static ?string $title = 'Merchant Offer Reports (Finance)';
    protected static ?string $navigationLabel = 'Merchant Offer Reports (Finance)';

    protected static ?string $slug = 'merchant-offer-finance-reports';

    protected static string $view = 'filament.pages.merchant-offer-finance-reports';

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
                    $startDate      = isset($filters['period']['start_date']) ? Carbon::parse($filters['period']['start_date']) : null;
                    $endDate        = isset($filters['period']['end_date']) ? Carbon::parse($filters['period']['end_date']) : null;
                    $purchaseMethod = $filters['purchaseMethod']['value'];

                    // Generate the file name
                    $fileName = 'merchant_offer_finance_reports_' .
                        ($startDate ? $startDate->format('Ymd') : 'begin') . '-' .
                        ($endDate ? $endDate->format('Ymd') : date('Ymd')) .
                        '.xlsx';

                    // Pass filters to the export
                    return Excel::download(new MerchantOfferFinanceReportsExport($startDate, $endDate, $purchaseMethod), $fileName);
                })
        ];
    }

    protected function getTableColumns(): array
    {
        return [
            Columns\TextColumn::make('id')
                ->label('ID')
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
            Columns\TextColumn::make('koc_user_name')
                ->label('KOC Name')
                ->sortable()
                ->searchable(isGlobal: true, query: function (Builder $query, string $search): Builder {
                    return $query->where('koc_users.name', 'like', "%{$search}%");
                }), 
            Columns\TextColumn::make('koc_user_email')
                ->label('KOC Email address')
                ->sortable()
                ->searchable(isGlobal: true, query: function (Builder $query, string $search): Builder {
                    return $query->where('koc_users.email', 'like', "%{$search}%");
                }),
            Columns\TextColumn::make('koc_user_phone_no')
                ->label('KOC Phone No')
                ->sortable()
                ->searchable(isGlobal: true, query: function (Builder $query, string $search): Builder {
                    return $query->where('koc_users.phone_no', 'like', "%{$search}%");
                }),
            Columns\TextColumn::make('buyer_name')
                ->label('Buyer Name')
                ->sortable()
                ->searchable(isGlobal: true, query: function (Builder $query, string $search): Builder {
                    return $query->where('users.name', 'like', "%{$search}%");
                }),
            Columns\TextColumn::make('code')
                ->label('Voucher Code')
                ->sortable(),
            Columns\TextColumn::make('transaction_date')
                ->label('Transaction Date')
                ->sortable()
                ->sortable(query: function (Builder $query, string $direction): Builder {
                    return $query->orderBy('merchant_offer_user.created_at', $direction);
                }),
            Columns\BadgeColumn::make('purchase_method')
                ->label('Purchase Method')
                ->enum([
                    'points' => 'Funbox',
                    'fiat' => 'Cash'
                ])
                ->colors([
                    'secondary' => 'fiat',
                    'success' => 'points',
                ])
                ->sortable(),
            Columns\TextColumn::make('amount_total')
                ->label('Purchase Amount')
                ->sortable()
                ->formatStateUsing(function (?string $state, $record): string {
                    if ($state === null) {
                        return '-';
                    }

                    // Get the purchase method
                    $purchaseMethod = $record->purchase_method; 

                    if ($purchaseMethod === 'fiat') {
                        // Format the currency
                        $formatter = new NumberFormatter('en_MY', NumberFormatter::CURRENCY);
                        return $formatter->formatCurrency($state, 'MYR');
                    }else if ($purchaseMethod === 'points') {
                        return $state;
                    }
                }),
            Columns\TextColumn::make('transaction_no')
                ->label('Transaction No (MPAY)')
                ->sortable()
                ->searchable(isGlobal: true, query: function (Builder $query, string $search): Builder {
                    return $query->where('transactions.transaction_no', 'like', "%{$search}%");
                }),
            Columns\TextColumn::make('amount')
                ->label('Purchase Amount (MPAY)')
                ->sortable()
                ->formatStateUsing(function (?string $state): string {
                    if ($state === null) {
                        return '-';
                    }

                    // Format the currency
                    $formatter = new NumberFormatter('en_MY', NumberFormatter::CURRENCY);
                    return $formatter->formatCurrency($state, 'MYR');
                }),
            Columns\TextColumn::make('reference_no')
                ->label('Reference No (MPAY)')
                ->sortable()
                ->searchable(isGlobal: true, query: function (Builder $query, string $search): Builder {
                    return $query->where('transactions.gateway_transaction_id', 'like', "%{$search}%");
                }),
            Columns\TextColumn::make('payment_method')
                ->label('Payment Method (MPAY)')
                ->sortable()
                ->searchable(isGlobal: true, query: function (Builder $query, string $search): Builder {
                    return $query->where('transactions.payment_method', 'like', "%{$search}%");
                }),

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
                ->label('Transaction Date'),
            SelectFilter::make('purchaseMethod')  // Filter directly on the column name
                ->options([
                    'points' => 'Funbox',
                    'fiat' => 'Cash'
                ])
                ->label('Purchase Method')
                ->query(function ($query, $data) {
                    if (isset($data['value']) && !empty($data['value'])) {
                        $query->where('merchant_offer_user.purchase_method', $data['value']);
                    }
                }),
        ];
    }

    protected function getTableQuery(): Builder
    {
        $query = MerchantOfferVoucher::query()
            ->selectRaw('
                merchant_offer_vouchers.id AS id,
                merchant_offer_vouchers.code AS code,
                merchant_offers.name AS offer_name,
                merchant_offer_user.purchase_method AS purchase_method,
                merchant_offer_user.total AS amount_total,
                merchant_offer_user.created_at AS transaction_date,
                merchants.name AS merchant_name,
                merchants.brand_name AS brand_name,
                users.name AS buyer_name,
                koc_users.name AS koc_user_name,
                koc_users.email AS koc_user_email,
                koc_users.phone_no AS koc_user_phone_no,
                transactions.transaction_no AS transaction_no,
                transactions.amount AS amount,
                transactions.gateway_transaction_id AS reference_no,
                transactions.payment_method AS payment_method,
                merchant_offers.unit_price as funbox_quantity,
                merchant_offers.point_fiat_price as funbox_original,
                merchant_offers.discounted_point_fiat_price as funbox_selling,
                merchant_offers.fiat_price as price_original,
                merchant_offers.discounted_fiat_price as price_selling,
                CASE 
                    WHEN merchant_offers.point_fiat_price > 0 THEN 
                        ((1 - (merchant_offers.discounted_point_fiat_price / merchant_offers.point_fiat_price)) * 100)
                    ELSE 0
                END AS funbox_discount_rate,
                CASE 
                    WHEN merchant_offers.fiat_price > 0 THEN 
                        ((1 - (merchant_offers.discounted_fiat_price / merchant_offers.fiat_price)) * 100)
                    ELSE 0
                END AS discount_rate
            ')
            ->join('merchant_offers', 'merchant_offers.id', '=', 'merchant_offer_vouchers.merchant_offer_id')
            ->leftJoin('merchants', 'merchants.user_id', '=', 'merchant_offers.user_id')
            ->leftJoin('users AS koc_users', 'merchants.koc_user_id', '=', 'koc_users.id')
            ->join('users', 'merchant_offer_vouchers.owned_by_id', '=', 'users.id')
            ->join('merchant_offer_user', function ($join) {
                $join->on('merchant_offer_user.voucher_id', '=', 'merchant_offer_vouchers.id')
                    ->whereColumn('merchant_offer_user.user_id', '=', 'merchant_offer_vouchers.owned_by_id')
                    ->where('merchant_offer_user.status', '=', MerchantOfferClaim::CLAIM_SUCCESS);
            })
            ->leftJoin('transactions', 'transactions.transaction_no', '=', 'merchant_offer_user.transaction_no')
            ->whereNotNull('merchant_offer_vouchers.owned_by_id')
            ->groupBy([
                'merchant_offer_vouchers.id',
                'merchant_offer_vouchers.code',
                'merchant_offers.name',
                'merchant_offer_user.purchase_method',
                'merchant_offer_user.total',
                'merchant_offer_user.created_at',
                'merchants.name',
                'merchants.brand_name',
                'users.name',
                'koc_users.name',
                'koc_users.email',
                'koc_users.phone_no',
                'transactions.transaction_no',
                'transactions.amount',
                'transactions.gateway_transaction_id',
                'transactions.payment_method',
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
            ->orderBy('merchant_offer_user.created_at', 'DESC')
            ->orderBy('merchant_offers.name', 'DESC');

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
    }
}