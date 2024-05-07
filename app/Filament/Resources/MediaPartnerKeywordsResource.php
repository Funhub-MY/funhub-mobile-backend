<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MediaPartnerKeywordsResource\Pages;
use App\Filament\Resources\MediaPartnerKeywordsResource\RelationManagers;
use App\Models\MediaPartnerKeywords;
use Filament\Forms;
use Filament\Forms\Components\Card;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class MediaPartnerKeywordsResource extends Resource
{
    protected static ?string $model = MediaPartnerKeywords::class;

    protected static ?string $navigationIcon = 'heroicon-o-collection';

    protected static ?string $navigationGroup = 'Settings';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Card::make([
                    TextInput::make('keyword')
                        ->label('Name')
                        ->required()
                        ->placeholder('Enter the name of the keyword'),

                    // select blacklist or whitelist
                    Select::make('type')
                        ->label('Type')
                        ->options([
                            'blacklist' => 'Blacklist',
                            'whitelist' => 'Whitelist',
                        ])
                        ->required()
                        ->placeholder('Select the type of the keyword'),

                    // hidden auth user
                    TextInput::make('user_id')
                        ->default(auth()->user()->id)
                        ->hidden(),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\Text::make('keyword')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\Text::make('type')
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
            'index' => Pages\ListMediaPartnerKeywords::route('/'),
            'create' => Pages\CreateMediaPartnerKeywords::route('/create'),
            'edit' => Pages\EditMediaPartnerKeywords::route('/{record}/edit'),
        ];
    }
}
