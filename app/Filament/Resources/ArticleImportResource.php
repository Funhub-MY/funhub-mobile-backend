<?php

namespace App\Filament\Resources;

use Filament\Forms;
use Filament\Tables;
use Illuminate\Support\Str;
use Filament\Forms\Form;
use App\Models\ArticleImport;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\ArticleImportResource\Pages;
use Tapp\FilamentAuditing\RelationManagers\AuditsRelationManager;
use App\Filament\Resources\ArticleImportResource\RelationManagers;

class ArticleImportResource extends Resource
{
    protected static ?string $model = ArticleImport::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

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
                    ->dateTime('d/m/Y hia')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('rss_channel.channel_name'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (int $state): string => ArticleImport::STATUS[$state] ?? $state)
                    ->color(fn (int $state): string => match($state) {
                        0 => 'danger',
                        1 => 'success',
                        default => 'gray',
                    })
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
            AuditsRelationManager::class,
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
