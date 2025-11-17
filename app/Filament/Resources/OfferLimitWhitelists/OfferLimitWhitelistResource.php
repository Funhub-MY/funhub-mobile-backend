<?php

namespace App\Filament\Resources\OfferLimitWhitelists;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\OfferLimitWhitelists\Pages\ListOfferLimitWhitelists;
use App\Filament\Resources\OfferLimitWhitelists\Pages\CreateOfferLimitWhitelist;
use App\Filament\Resources\OfferLimitWhitelists\Pages\EditOfferLimitWhitelist;
use App\Filament\Resources\OfferLimitWhitelistResource\Pages;
use App\Filament\Resources\OfferLimitWhitelistResource\RelationManagers;
use App\Models\OfferLimitWhitelist;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class OfferLimitWhitelistResource extends Resource
{
    protected static ?string $model = OfferLimitWhitelist::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static string | \UnitEnum | null $navigationGroup = 'Merchant Offers';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make([
                    Select::make('user_id')
                        ->relationship('user', 'name')
                        ->searchable()
                        ->required(),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->searchable()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
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
            'index' => ListOfferLimitWhitelists::route('/'),
            'create' => CreateOfferLimitWhitelist::route('/create'),
            'edit' => EditOfferLimitWhitelist::route('/{record}/edit'),
        ];
    }
}
