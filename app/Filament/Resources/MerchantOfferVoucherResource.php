<?php

namespace App\Filament\Resources;

use Filament\Forms;
use Filament\Tables;
use Illuminate\Support\Str;
use Filament\Resources\Form;
use Filament\Resources\Table;
use Filament\Resources\Resource;
use App\Models\MerchantOfferClaim;
use Filament\Forms\Components\Card;
use Filament\Tables\Actions\Action;
use Filament\Tables\Filters\Filter;
use Illuminate\Support\Facades\Log;
use Pages\ViewMerchantOfferVoucher;
use App\Models\MerchantOfferVoucher;
use Filament\Forms\Components\Select;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Model;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\DateTimePicker;
use pxlrbt\FilamentExcel\Exports\ExcelExport;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use pxlrbt\FilamentExcel\Actions\Tables\ExportAction;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use App\Filament\Resources\MerchantOfferVoucherResource\Pages;
use Tapp\FilamentAuditing\RelationManagers\AuditsRelationManager;
use App\Filament\Resources\MerchantOfferVoucherResource\RelationManagers;
use App\Filament\Resources\MerchantOfferVoucherResource\Pages\MerchantOfferVouchersRelationManager;

class MerchantOfferVoucherResource extends Resource
{
    protected static ?string $model = MerchantOfferVoucher::class;

    protected static ?string $navigationIcon = 'heroicon-o-collection';

    protected static ?string $modelLabel = 'Stock Voucher';

    protected static ?string $navigationGroup = 'Merchant';

    protected static ?int $navigationSort = 3;

    public static function getEloquentQuery(): Builder
    {
        $query = static::getModel()::query();
        if (auth()->user()->hasRole('merchant')) {
            // whereHas offer with user_id = auth()->id()
            $query->whereHas('merchant_offer', function ($q) {
                $q->where('user_id', auth()->id());
            });
        }

        return $query;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Card::make([
                    Select::make('merchant_offer_id')
                        ->label('Attached to Merchant Offer')
                        ->relationship('merchant_offer', 'name')
                        ->preload()
                        ->searchable(),

                    Forms\Components\TextInput::make('code')
                        ->required()
                        ->disabled()
                        ->default(fn () => MerchantOfferVoucher::generateCode())
                        ->helperText('Auto-generated')
                        ->maxLength(255),
                ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Voucher Code')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('merchant_offer.name')
                    ->label('Merchant Offer')
                    ->formatStateUsing(function ($state) {
                        return Str::limit($state, 20, '...') ?? '-';
                    })
                    ->sortable()
                    ->searchable(),

                // sku
                TextColumn::make('merchant_offer.sku')
                ->label('SKU')
                ->sortable()
                ->searchable(),

                // financial status (claim status)
                Tables\Columns\BadgeColumn::make('latestSuccessfulClaim.status')
                    ->label('Financial Status')
                    ->default(0)
                    ->sortable()
                    ->enum([
                        0 => 'Unclaimed',
                        1 => MerchantOfferClaim::CLAIM_STATUS[1],
                        2 => MerchantOfferClaim::CLAIM_STATUS[2],
                        3 => MerchantOfferClaim::CLAIM_STATUS[3],
                    ])
                    ->colors([
                        'secondary' => 0,
                        'success' => 1,
                        'danger' => 2,
                        'warning' => 3,
                    ]),

                // redemptions status
                Tables\Columns\BadgeColumn::make('voucher_redeemed')
                    ->label('Redemption Status')
                    ->default(0)
                    ->enum([
                        false => 'Not Redeemed',
                        true => 'Redeemed'
                    ])
                    ->colors([
                        'secondary' => false,
                        'success' => true,
                    ]),

                Tables\Columns\TextColumn::make('owner.name')
                    ->label('Purchased By')
                    ->default('-')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('latestSuccessfulClaim.purchase_method')
                    ->label('Purchase Method')
                    ->formatStateUsing(function ($state) {
                        if ($state == 'fiat') {
                            return 'Cash';
                        } else if ($state == 'points') {
                            return 'Funhub';
                        } else {
                            return '-';
                        }
                    })
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('latestSuccessfulClaim.net_amount')
                    ->formatStateUsing(function ($state) {
                        if ($state) {
                            return number_format($state, 2);
                        } else {
                            return '-';
                        }
                    })
                    ->label('Amount'),

                Tables\Columns\TextColumn::make('latestSuccessfulClaim.created_at')
                    ->label('Purchased At')
                    ->date('d/m/Y h:ia')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('voided')
                    ->label('Voided')
                    ->formatStateUsing(function ($state) {
                        switch ($state) {
                            case 0:
                                return 'False';
                                break;
                            case 1:
                                return 'True';
                                break;
                        }
                    })
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('claimStatus')
                    // ->options(MerchantOfferClaim::CLAIM_STATUS)
                    ->options([
                        null => 'Unclaimed',
                        1 => MerchantOfferClaim::CLAIM_STATUS[1],
                        2 => MerchantOfferClaim::CLAIM_STATUS[2],
                        3 => MerchantOfferClaim::CLAIM_STATUS[3],
                    ])
                    // ->relationship('claim', 'status')
                    ->query(function (Builder $query, array $data) {
                        if ($data['value'] == null) {
                            $query->whereDoesntHave('latestSuccessfulClaim');
                        } else {
                            $query->whereHas('latestSuccessfulClaim', function ($q) use ($data) {
                                $q->where('status', $data['value']);
                            });
                        }
                    })
                    ->label('Financial Status'),
                SelectFilter::make('getVoucherRedeemedAttribute')
                    // ->relationship('redeem', 'id')
                    ->options([
                        false => 'Not Redeemed',
                        true => 'Redeemed'
                    ])
                    ->query(function (Builder $query, array $data) {
                        if ($data['value'] == false) {
                            $query->whereDoesntHave('redeem');
                        } else {
                            $query->whereHas('redeem');
                        }
                    })
                    ->label('Redemption Status'),
                SelectFilter::make('merchant_offer_id')
                    ->relationship('merchant_offer', 'name')
                    ->searchable()
                    ->label('Merchant Offer'),
            ])
            ->actions([
                ViewAction::make(),
                Action::make('voided')
                    ->label('Void')
                    // ->visible(fn () => auth()->user()->hasRole('super_admin'))
                    ->visible(function (Model $record) {
                        return auth()->user()->hasRole('super_admin') &&
                            $record->latestSuccessfulClaim &&
                            $record->latestSuccessfulClaim->status == MerchantOfferClaim::CLAIM_SUCCESS &&
                            !$record->voucher_redeemed;
                    })
                    ->action(function (Model $record) {
                        // Set 'voided' to true
                        $record->update([
                            'voided' => true,
                            'owned_by_id' => null,
                        ]);

                        MerchantOfferClaim::where('voucher_id', $record->id)
                            ->update([
                                // 'voucher_id' => null, // Update 'voucher_id' in merchant_offer_user table to null --> Financial status will revert back to false ('Not Redeemed'),
                                'status' => MerchantOfferClaim::CLAIM_FAILED, // Update claim_status in merchant_offer_user table to failed
                            ]);
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Void Claimed Voucher')
                    ->modalSubheading('Are you sure you\'d like to void this claimed voucher? This cannot be undone.')
                    ->modalButton('Yes, void claimed voucher')
                // Action::make('claim')
                //     ->visible(fn () => auth()->user()->hasRole('merchant'))
                //     ->requiresConfirmation()
                //     ->form([
                //         TextInput::make('unique_code')
                //             ->label('Unique Code Generated on Customer\'s App')
                //             ->placeholder('ABCDE')
                //             ->required(),
                //         DateTimePicker::make('claim_date_time')
                //             ->default(now())
                //             ->disabled()
                //             ->required()
                //             ->label('Claim Date Time'),
                //         Placeholder::make('disclaimer')
                //             ->label('Disclaimer: Once click confirmed, the voucher will be marked as claimed and is not reversible.')
                //     ])
                //     ->action(function (MerchantOfferVoucher $record, array $data) {
                //         $offer = $record->merchant_offer;

                //         // get claim id of the user via

                //         // merchant code validated proceed create redeems
                //         // $redeem = $offer->redeems()->attach(auth()->user()->id, [
                //         //     'claim_id' => $request->claim_id,
                //         //     'quantity' => $request->quantity,
                //         // ]);
                //     }),
            ])
            ->bulkActions([
                ExportBulkAction::make()->exports([
                    ExcelExport::make('table')->fromTable(),
                ])
            ]);
    }

    public static function getRelations(): array
    {
        return [
            MerchantOfferVouchersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMerchantOfferVouchers::route('/'),
            'create' => Pages\CreateMerchantOfferVoucher::route('/create'),
            'view' => Pages\ViewMerchantOffers::route('/{record}'),
            'edit' => Pages\EditMerchantOfferVoucher::route('/{record}/edit'),
        ];
    }
}
