<?php

namespace App\Filament\Resources\ArticleFeedWhitelistUsers;

use Filament\Schemas\Schema;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\ArticleFeedWhitelistUsers\Pages\ListArticleFeedWhitelistUsers;
use App\Filament\Resources\ArticleFeedWhitelistUsers\Pages\CreateArticleFeedWhitelistUser;
use App\Filament\Resources\ArticleFeedWhitelistUsers\Pages\EditArticleFeedWhitelistUser;
use App\Filament\Resources\ArticleFeedWhitelistUserResource\Pages;
use App\Filament\Resources\ArticleFeedWhitelistUserResource\RelationManagers;
use App\Models\ArticleFeedWhitelistUser;
use Filament\Forms;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ArticleFeedWhitelistUserResource extends Resource
{
    protected static ?string $model = ArticleFeedWhitelistUser::class;

    protected static string | \UnitEnum | null $navigationGroup = 'Settings';

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->label('Username')
                ->searchable()
                ->relationship('user', 'username', fn (Builder $query) => $query->doesntHave('articleFeedWhitelist'))
                ->getOptionLabelFromRecordUsing(fn (Model $record) => "{$record->name} ({$record->username}) (ID: {$record->id})")
                    ->helperText('Anyone in whitelist will be included in Home recommendation feed.')
                    ->required(),

                Hidden::make('created_by_id')
                    ->default(auth()->id()),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.id')
                    ->label('User ID')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('user.name')
                    ->label('User')
                    ->searchable()
                    ->sortable(),


                TextColumn::make('user.username')
                    ->label('User Username')
                    ->searchable()
                    ->sortable(),


                TextColumn::make('created_at')
                    ->label('Created At')
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->label('Updated At')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make()
                    ->requiresConfirmation(),
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
            'index' => ListArticleFeedWhitelistUsers::route('/'),
            'create' => CreateArticleFeedWhitelistUser::route('/create'),
            'edit' => EditArticleFeedWhitelistUser::route('/{record}/edit'),
        ];
    }
}
