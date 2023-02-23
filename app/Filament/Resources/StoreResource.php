<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StoreResource\Pages;
use App\Filament\Resources\StoreResource\RelationManagers;
use App\Models\Merchant;
use App\Models\Store;
use App\Models\User;
use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class StoreResource extends Resource
{
    protected static ?string $model = Store::class;

    protected static ?string $navigationIcon = 'heroicon-o-library';

    protected static ?string $navigationGroup = 'Merchant';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Basic Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Store Name')
                            ->autofocus()
                            ->required()
                            ->rules('required', 'max:255'),
                        Forms\Components\Select::make('user_id')
                            ->label('Belongs To User')
                            ->preload()
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search) => User::where('name', 'like', "%{$search}%")->limit(25))
                            ->getOptionLabelUsing(fn ($value): ?string => User::find($value)?->name)
                            ->default(fn () => User::where('id', auth()->user()->id)?->first()->id)
                            ->relationship('user','name')
                    ]),
                Forms\Components\Section::make('Store Information')
                    ->schema([
                        Forms\Components\TextInput::make('business_phone_no')
                            ->label('Store Phone Number'),
                        Forms\Components\Textarea::make('address')
                            ->required(),
                        Forms\Components\TextInput::make('address_postcode')
                            ->required(),
                        Forms\Components\TextInput::make('lang')
                            ->helperText('This is to locate your store in the map.')
                            ->required(),
                        Forms\Components\TextInput::make('long')
                            ->helperText('This is to locate your store in the map.')
                            ->required(),
                        Forms\Components\Toggle::make('is_hq')
                            ->label('Is headquarter ?')
                            ->onIcon('heroicon-s-check-circle')
                            ->offIcon('heroicon-s-x-circle')
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name'),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('By User'),
                Tables\Columns\TextColumn::make('business_phone_no'),
                Tables\Columns\TextColumn::make('address'),
                Tables\Columns\TextColumn::make('address_postcode'),
                Tables\Columns\TextColumn::make('lang'),
                Tables\Columns\TextColumn::make('long'),
                Tables\Columns\ToggleColumn::make('is_hq')
                    ->label('Headquarter')
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
            RelationManagers\MerchantOffersRelationManager::class
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStores::route('/'),
            'create' => Pages\CreateStore::route('/create'),
            'edit' => Pages\EditStore::route('/{record}/edit'),
        ];
    }
}
