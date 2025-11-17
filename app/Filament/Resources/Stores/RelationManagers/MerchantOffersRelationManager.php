<?php

namespace App\Filament\Resources\Stores\RelationManagers;

use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextArea;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use App\Models\Store;
use App\Models\User;
use Filament\Forms;
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

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search) => User::where('name', 'like', "%{$search}%")->limit(25))
                    ->getOptionLabelUsing(fn ($value): ?string => User::find($value)?->name)
                    ->default(fn () => User::where('id', auth()->user()->id)?->first()->id)
                    ->required()
                    ->reactive()
                    ->afterStateUpdated(fn (callable $set) => $set('store_id', null))
                    ->relationship('user', 'name'),
                TextInput::make('name')
                    ->required(),
                TextInput::make('unit_price')
                    ->required()
                    ->numeric()
                    ->label('Unit Price')
                    ->prefix('RM')
                    ->step(0.01)
                    ->minValue(1),
                TextInput::make('quantity')
                    ->required()
                    ->numeric()
                    ->minValue(1),
                TextInput::make('sku')
                    ->label('SKU')
                    ->required(),
                DateTimePicker::make('available_at')
                    ->required(),
                DateTimePicker::make('available_until')
                    ->required(),
                TextArea::make('description')
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
                TextColumn::make('name'),
                TextColumn::make('description'),
                TextColumn::make('unit_price'),
                TextColumn::make('available_at'),
                TextColumn::make('available_until'),
                TextColumn::make('quantity'),
                TextColumn::make('sku'),
                ToggleColumn::make('claimed'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }
}
