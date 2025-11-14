<?php

namespace App\Filament\Resources\StoreResource\RelationManagers;

use App\Models\Store;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class MerchantOffersRelationManager extends RelationManager
{
    protected static string $relationship = 'merchant_offers';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $modelLabel = 'Merchant Offer';

    public function form(Form $form): Form
    {
        return $form
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
                Forms\Components\TextInput::make('name')
                    ->required(),
                Forms\Components\TextInput::make('unit_price')
                    ->required()
                    ->numeric()
                    ->label('Unit Price')
                    ->prefix('RM')
                    ->step(0.01)
                    ->minValue(1),
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
                    ->helperText('By creating offer from here will only applicable to the current store.')
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name'),
                Tables\Columns\TextColumn::make('description'),
                Tables\Columns\TextColumn::make('unit_price'),
                Tables\Columns\TextColumn::make('available_at'),
                Tables\Columns\TextColumn::make('available_until'),
                Tables\Columns\TextColumn::make('quantity'),
                Tables\Columns\TextColumn::make('sku'),
                Tables\Columns\ToggleColumn::make('claimed'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }
}
