<?php

namespace App\Filament\Resources\VoucherMovements;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use App\Filament\Resources\VoucherMovements\Pages\ListVoucherMovements;
use Illuminate\Database\Eloquent\Model;
use App\Filament\Resources\VoucherMovementResource\Pages;
use App\Models\MerchantOfferVoucherMovement;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Filters\Filter;

class VoucherMovementResource extends Resource
{
    protected static ?string $model = MerchantOfferVoucherMovement::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static string | \UnitEnum | null $navigationGroup = 'Merchant Offers';

    protected static ?string $modelLabel = 'Voucher Movement';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make([
                    Select::make('from_merchant_offer_id')
                        ->label('From Merchant Offer')
                        ->relationship('fromMerchantOffer', 'name')
                        ->url(fn ($record) => route('filament.admin.resources.merchant-offers.edit', $record->fromMerchantOffer))
                        ->required()
                        ->searchable()
                        ->preload(),

                    Select::make('to_merchant_offer_id')
                        ->label('To Merchant Offer')
                        ->relationship('toMerchantOffer', 'name')
                        ->url(fn ($record) => route('filament.admin.resources.merchant-offers.edit', $record->fromMerchantOffer))
                        ->required()
                        ->searchable()
                        ->preload(),

                    Select::make('voucher_id')
                        ->label('Voucher')
                        ->relationship('voucher', 'code')
                        ->required()
                        ->searchable()
                        ->preload(),

                    Select::make('user_id')
                        ->label('Moved By')
                        ->relationship('user', 'name')
                        ->required()
                        ->searchable()
                        ->preload(),

                    Textarea::make('remarks')
                        ->label('Remarks')
                        ->rows(3)
                        ->nullable(),
                ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('voucher.code')
                    ->label('Voucher Code')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('fromMerchantOffer.name')
                    ->label('From Merchant Offer')
                    ->description(fn ($record) => $record->fromMerchantOffer->sku ?? '')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('toMerchantOffer.name')
                    ->label('To Merchant Offer')
                    ->description(fn ($record) => $record->toMerchantOffer->sku ?? '')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('user.name')
                    ->label('Moved By')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('remarks')
                    ->label('Remarks')
                    ->wrap()
                    ->limit(50),

                TextColumn::make('created_at')
                    ->label('Moved At')
                    ->dateTime('d/m/Y h:ia')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('from_merchant_offer')
                    ->label('From Merchant Offer')
                    ->relationship('fromMerchantOffer', 'name')
                    ->searchable(),

                SelectFilter::make('to_merchant_offer')
                    ->label('To Merchant Offer')
                    ->relationship('toMerchantOffer', 'name')
                    ->searchable(),

                SelectFilter::make('user')
                    ->label('Moved By')
                    ->relationship('user', 'name')
                    ->searchable(),

                Filter::make('created_at_from')
                    ->schema([
                        DatePicker::make('created_at')
                            ->label('Moved From')
                            ->placeholder('Select start date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['created_at'],
                            fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                        );
                    }),

                Filter::make('created_at_from_to')
                    ->schema([
                        DatePicker::make('created_at')
                            ->label('Moved Until')
                            ->placeholder('Select end date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['created_at'],
                            fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                        );
                    }),
            ])
            ->recordActions([
                // Tables\Actions\ViewAction::make(),
            ])
            ->toolbarActions([
                // Removing delete bulk action since these are important audit records
            ])
            ->defaultSort('created_at', 'desc');
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
            'index' => ListVoucherMovements::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(MerchantOfferVoucherMovement|Model $record): bool
    {
        return false;
    }

    public static function canDelete(MerchantOfferVoucherMovement|Model $record): bool
    {
        return false;
    }
}
