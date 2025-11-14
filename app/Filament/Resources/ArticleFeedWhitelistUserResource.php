<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ArticleFeedWhitelistUserResource\Pages;
use App\Filament\Resources\ArticleFeedWhitelistUserResource\RelationManagers;
use App\Models\ArticleFeedWhitelistUser;
use Filament\Forms;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
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

    protected static ?string $navigationGroup = 'Settings';

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
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
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make()
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
            'index' => Pages\ListArticleFeedWhitelistUsers::route('/'),
            'create' => Pages\CreateArticleFeedWhitelistUser::route('/create'),
            'edit' => Pages\EditArticleFeedWhitelistUser::route('/{record}/edit'),
        ];
    }
}
