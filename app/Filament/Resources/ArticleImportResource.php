<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ArticleImportResource\Pages;
use App\Filament\Resources\ArticleImportResource\RelationManagers;
use App\Models\ArticleImport;
use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;

class ArticleImportResource extends Resource
{
    protected static ?string $model = ArticleImport::class;

    protected static ?string $navigationIcon = 'heroicon-o-collection';

    protected static ?string $navigationGroup = 'Articles';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Details')->schema([
                            Forms\Components\Select::make('RSS Channel')
                                ->relationship('rss_channel', 'channel_name')
                                ->disabled(),
                            Forms\Components\Select::make('status')
                                ->options(ArticleImport::STATUS)
                                ->disabled(),
                            Forms\Components\DateTimePicker::make('last_run_at')
                                ->disabled(),
                        ])
                    ]),
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Import logs')->schema([
                            Forms\Components\Textarea::make('description')
                                ->rows(5)
                                ->cols(10)
                                ->disabled(),
                        ])
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // time stamp
                Tables\Columns\TextColumn::make('last_run_at')
                    ->format(function ($value) {
                        return $value->diffForHumans();
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('rss_channel.channel_name'),
                Tables\Columns\BadgeColumn::make('status')
                    ->enum(ArticleImport::STATUS)
                    ->colors([
                        'danger' => 0,
                        'success' => 1,
                    ])
                    ->sortable(),
                Tables\Columns\TextColumn::make('description')
                    ->limit(80),
            ])
            ->filters([
                //
            ])
            ->actions([
                //Tables\Actions\EditAction::make(),
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                //Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ArticlesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListArticleImports::route('/'),
            'create' => Pages\CreateArticleImport::route('/create'),
            'view' => Pages\ViewArticleImport::route('/{record}'),
            'edit' => Pages\EditArticleImport::route('/{record}/edit'),
        ];
    }
}
