<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MerchantOfferResource\Pages;
use App\Filament\Resources\MerchantOfferResource\RelationManagers;
use App\Models\Merchant;
use App\Models\MerchantOffer;
use App\Models\Store;
use App\Models\User;
use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class MerchantOfferResource extends Resource
{
    protected static ?string $model = MerchantOffer::class;

    protected static ?string $navigationIcon = 'heroicon-o-cash';

    protected static ?string $modelLabel = 'Merchant Offer';

    protected static ?string $navigationGroup = 'Merchant';

    protected static ?int $navigationSort = 2;
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Card::make()
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->required(),
                                Forms\Components\TextInput::make('unit_price')
                                    ->required()
                                    ->numeric()
                                    ->mask(fn (Forms\Components\TextInput\Mask $mask) => $mask
                                        ->numeric()
                                        ->decimalPlaces(2)
                                        ->minValue(1)
                                        ->thousandsSeparator(',')
                                    ),
                                Forms\Components\TextInput::make('quantity')
                                    ->required()
                                    ->numeric()
                                    ->minValue(1),
                                Forms\Components\TextInput::make('sku')
                                    ->label('SKU')
                                    ->required(),
                                Forms\Components\DateTimePicker::make('available_at')
                                    ->required(),
                                Forms\Components\DateTimePicker::make('available_until')
                                    ->required(),
                                Forms\Components\TextArea::make('description')
                                    ->rows(5)
                                    ->cols(10)
                                    ->columnSpan('full')
                                    ->required(),
                            ])->columns(2),
                    ])->columnSpan(['lg' => 2]),
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Owners')
                            ->schema([
                                Forms\Components\Select::make('user_id')
                                    ->searchable()
                                    ->getSearchResultsUsing(fn (string $search) => User::where('name', 'like', "%{$search}%")->limit(25))
                                    ->getOptionLabelUsing(fn ($value): ?string => User::find($value)?->name)
                                    ->default(fn () => User::where('id', auth()->user()->id)?->first()->id)
                                    ->required()
                                    ->reactive()
                                    ->afterStateUpdated(fn (callable $set) => $set('store_id', null))
                                    ->relationship('user', 'name'),
                                Forms\Components\Select::make('store_id')
                                    ->options(function (callable $get) {
                                        $user = User::where('id', $get('user_id'))->first();
                                        if ($user) {
                                            return $user->stores->pluck('name', 'id');
                                        }
                                        // TODO:: pluck all first until permissions and roles is up and running.
                                        return Store::all()->pluck('id', 'name');
                                    })
                                    ->searchable()
                                    ->label('Store')
                                    ->helperText('By selecting this will make the offers only applicable to the selected store.')
                                    ->nullable()
                            ])->columns(1),
                    ])->columnSpan(['lg' => 1]),
            ])
            ->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name'),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('By User'),
                Tables\Columns\TextColumn::make('store.name')
                    ->label('By Store'),
                Tables\Columns\TextColumn::make('description'),
                Tables\Columns\TextColumn::make('unit_price'),
                Tables\Columns\TextColumn::make('available_at'),
                Tables\Columns\TextColumn::make('available_until'),
                Tables\Columns\TextColumn::make('quantity'),
                Tables\Columns\TextColumn::make('sku')
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //RelationManagers\ClaimedByUsersRelationManager::class,
            RelationManagers\UsersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMerchantOffers::route('/'),
            'create' => Pages\CreateMerchantOffer::route('/create'),
            'edit' => Pages\EditMerchantOffer::route('/{record}/edit'),
        ];
    }
}
