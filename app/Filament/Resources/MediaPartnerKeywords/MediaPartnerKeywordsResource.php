<?php

namespace App\Filament\Resources\MediaPartnerKeywords;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\MediaPartnerKeywords\Pages\ListMediaPartnerKeywords;
use App\Filament\Resources\MediaPartnerKeywords\Pages\CreateMediaPartnerKeywords;
use App\Filament\Resources\MediaPartnerKeywords\Pages\EditMediaPartnerKeywords;
use App\Filament\Resources\MediaPartnerKeywordsResource\Pages;
use App\Filament\Resources\MediaPartnerKeywordsResource\RelationManagers;
use App\Models\MediaPartnerKeywords;
use Filament\Forms;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class MediaPartnerKeywordsResource extends Resource
{
    protected static ?string $model = MediaPartnerKeywords::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static string | \UnitEnum | null $navigationGroup = 'Settings';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make([
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
                    Hidden::make('user_id')
                        ->default(auth()->user()->id),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('keyword')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('type')
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
            'index' => ListMediaPartnerKeywords::route('/'),
            'create' => CreateMediaPartnerKeywords::route('/create'),
            'edit' => EditMediaPartnerKeywords::route('/{record}/edit'),
        ];
    }
}
