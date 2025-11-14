<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OfferLimitWhitelistResource\Pages;
use App\Filament\Resources\OfferLimitWhitelistResource\RelationManagers;
use App\Models\OfferLimitWhitelist;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class OfferLimitWhitelistResource extends Resource
{
    protected static ?string $model = OfferLimitWhitelist::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Merchant Offers';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
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
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOfferLimitWhitelists::route('/'),
            'create' => Pages\CreateOfferLimitWhitelist::route('/create'),
            'edit' => Pages\EditOfferLimitWhitelist::route('/{record}/edit'),
        ];
    }
}
