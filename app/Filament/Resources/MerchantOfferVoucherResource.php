<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MerchantOfferVoucherResource\Pages;
use App\Filament\Resources\MerchantOfferVoucherResource\RelationManagers;
use App\Models\MerchantOfferClaim;
use App\Models\MerchantOfferVoucher;
use Filament\Forms;
use Filament\Forms\Components\Card;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;

class MerchantOfferVoucherResource extends Resource
{
    protected static ?string $model = MerchantOfferVoucher::class;

    protected static ?string $navigationIcon = 'heroicon-o-collection';

    protected static ?string $modelLabel = 'Stock Voucher';

    protected static ?string $navigationGroup = 'Merchant';

    protected static ?int $navigationSort = 3;

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
                TextColumn::make('merchant_offer.name')
                    ->label('Merchant Offer')
                    ->formatStateUsing(function ($state) {
                        return Str::limit($state, 20, '...') ?? '-';
                    })
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('code')
                    ->searchable(),

                Tables\Columns\BadgeColumn::make('claim.status')
                    ->label('Status')
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

                Tables\Columns\TextColumn::make('owner.name')
                    ->label('Claimed By')
                    ->default('-')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('claim.purchase_method')
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

                Tables\Columns\TextColumn::make('claim.net_amount')
                    ->formatStateUsing(function ($state) {
                        if ($state) {
                            return number_format($state, 2);
                        } else {
                            return '-';
                        }
                    })
                    ->label('Amount'),

                Tables\Columns\TextColumn::make('claim.created_at')
                    ->label('Claimed At')
                    ->date('d/m/Y h:ia')
                    ->searchable()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('claim.status')
                    ->options(MerchantOfferClaim::CLAIM_STATUS)
                    ->label('Status'),
                SelectFilter::make('merchant_offer_id')
                    ->relationship('merchant_offer', 'name')
                    ->searchable()
                    ->label('Merchant Offer'),
            ])
            ->actions([
                // Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                // Tables\Actions\DeleteBulkAction::make(),
            ]);
    }
    
    public static function getRelations(): array
    {
        return [
            //
        ];
    }
    
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMerchantOfferVouchers::route('/'),
            'create' => Pages\CreateMerchantOfferVoucher::route('/create'),
            'edit' => Pages\EditMerchantOfferVoucher::route('/{record}/edit'),
        ];
    }    
}
