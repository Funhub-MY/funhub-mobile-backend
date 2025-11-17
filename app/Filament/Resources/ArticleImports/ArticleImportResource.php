<?php

namespace App\Filament\Resources\ArticleImports;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\ViewAction;
use App\Filament\Resources\ArticleImports\RelationManagers\ArticlesRelationManager;
use App\Filament\Resources\ArticleImports\Pages\ListArticleImports;
use App\Filament\Resources\ArticleImports\Pages\CreateArticleImport;
use App\Filament\Resources\ArticleImports\Pages\ViewArticleImport;
use App\Filament\Resources\ArticleImports\Pages\EditArticleImport;
use Filament\Forms;
use Filament\Tables;
use Illuminate\Support\Str;
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

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static string | \UnitEnum | null $navigationGroup = 'Articles';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Group::make()
                    ->schema([
                        Section::make('Details')->schema([
                            Select::make('RSS Channel')
                                ->relationship('rss_channel', 'channel_name')
                                ->disabled(),
                            Select::make('status')
                                ->options(ArticleImport::STATUS)
                                ->disabled(),
                            DateTimePicker::make('last_run_at')
                                ->disabled(),
                        ])
                    ]),
                Group::make()
                    ->schema([
                        Section::make('Import logs')->schema([
                            Textarea::make('description')
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
                TextColumn::make('last_run_at')
                    ->dateTime('d/m/Y hia')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('rss_channel.channel_name'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (int $state): string => ArticleImport::STATUS[$state] ?? $state)
                    ->color(fn (int $state): string => match($state) {
                        0 => 'danger',
                        1 => 'success',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('description')
                    ->limit(80),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                //Tables\Actions\EditAction::make(),
                ViewAction::make(),
            ])
            ->toolbarActions([
                //Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ArticlesRelationManager::class,
            AuditsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListArticleImports::route('/'),
            'create' => CreateArticleImport::route('/create'),
            'view' => ViewArticleImport::route('/{record}'),
            'edit' => EditArticleImport::route('/{record}/edit'),
        ];
    }
}
