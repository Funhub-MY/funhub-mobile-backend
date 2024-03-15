<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SearchKeywordResource\Pages;
use App\Filament\Resources\SearchKeywordResource\RelationManagers;
use App\Filament\Resources\SearchKeywordResource\RelationManagers\ArticlesRelationManager;
use App\Models\SearchKeyword;
use Filament\Forms;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SearchKeywordResource extends Resource
{
    protected static ?string $model = SearchKeyword::class;

    protected static ?string $navigationGroup = 'Settings';

    protected static ?string $navigationIcon = 'heroicon-o-search';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Search Keyword Information')
                    ->schema([
                        TextInput::make('keyword')
                            ->label('Keyword')
                            ->required(),

                        Textarea::make('description')
                            ->label('Description'),

                        Toggle::make('blacklisted')
                            ->label('Blacklisted')
                            ->default(false)
                            ->helperText('Blacklisted keywords will not be searchable'),
                    ]),
                Section::make('Sponsored Information')
                    ->schema([
                        Placeholder::make('sponsored_placeholder')
                            ->label('Sponsored keywords will rank higher than non-sponsored, leave the fields below empty if the keyword is not sponsored'),

                        DateTimePicker::make('sponsored_from')
                            ->label('Sponsored From'),

                        DateTimePicker::make('sponsored_to')
                            ->label('Sponsored To')
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('keyword')
                    ->label('Keyword')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('article_counts')
                    ->counts('articles')
                    ->sortable()
                    ->default(0)
                    ->label('Linked Articles'),

                TextColumn::make('hits')
                    ->sortable()
                    ->label('Hits'),

                ToggleColumn::make('blacklisted')
                    ->label('Blacklisted')
                    ->sortable(),

                TextColumn::make('sponsored_from')
                    ->label('Sponsored From')
                    ->sortable(),

                TextColumn::make('sponsored_to')
                    ->label('Sponsored To')
                    ->sortable(),
            ])
            ->filters([
                // sponsored only means theres value for sponsored from and to
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
            ArticlesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSearchKeywords::route('/'),
            'create' => Pages\CreateSearchKeyword::route('/create'),
            'edit' => Pages\EditSearchKeyword::route('/{record}/edit'),
        ];
    }
}
