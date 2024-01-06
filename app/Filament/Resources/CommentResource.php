<?php

namespace App\Filament\Resources;

use Filament\Forms;
use App\Models\User;
use Filament\Tables;
use App\Models\Article;
use App\Models\Comment;
use Filament\Resources\Form;
use App\Policies\AuditPolicy;
use Filament\Resources\Table;
use Filament\Resources\Resource;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\CommentResource\Pages;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\CommentResource\RelationManagers;
use Tapp\FilamentAuditing\RelationManagers\AuditsRelationManager;

class CommentResource extends Resource
{
    protected static ?string $model = Comment::class;

    protected static ?string $navigationIcon = 'heroicon-o-collection';

    protected static ?string $navigationGroup = 'Users';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('body')
                    ->label('Comment'),
                Forms\Components\Select::make('status')
                    ->options(Comment::STATUS)
                    ->default(0)
                    ->required(),
                Forms\Components\Select::make('user_id')
                    ->label('Belongs To User')
                    ->preload()
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search) => User::where('name', 'like', "%{$search}%")->limit(25))
                    ->getOptionLabelUsing(fn ($value): ?string => User::find($value)?->name)
                    ->default(fn () => User::where('id', auth()->user()->id)?->first()->id)
                    ->relationship('user','name'),
                Forms\Components\MorphToSelect::make('commentable')
                    ->types([
                        // TODO:: at the moment there is only 1 type.
                        Forms\Components\MorphToSelect\Type::make(Article::class)->titleColumnName('title'),
                    ])
                    ->label('Type'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('By User'),
                Tables\Columns\TextColumn::make('body')
                    ->label('Comments')
                    ->words(40)
                    ->wrap(),
                Tables\Columns\BadgeColumn::make('status')
                    ->enum([
                        0 => 'Draft',
                        1 => 'Published',
                        2 => 'Hidden',
                    ])
                    ->colors([
                        'primary' => static fn ($state): bool => $state === 0,
                        'success' => static fn ($state): bool => $state === 1,
                        'danger' => static fn ($state): bool => $state === 2,
                    ]),
                Tables\Columns\TextColumn::make('created_at'),
                Tables\Columns\TextColumn::make('updated_at'),
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
            AuditsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListComments::route('/'),
            'create' => Pages\CreateComment::route('/create'),
            'edit' => Pages\EditComment::route('/{record}/edit'),
        ];
    }
}
