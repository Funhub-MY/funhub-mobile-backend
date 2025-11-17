<?php

namespace App\Filament\Resources\ArticleEngagements;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use App\Models\Article;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\DatePicker;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\ArticleEngagements\Pages\ListArticleEngagements;
use App\Filament\Resources\ArticleEngagements\Pages\CreateArticleEngagement;
use App\Filament\Resources\ArticleEngagements\Pages\EditArticleEngagement;
use App\Filament\Resources\ArticleEngagementResource\Pages;
use App\Filament\Resources\ArticleEngagementResource\RelationManagers;
use App\Models\ArticleEngagement;
use App\Models\User;
use Closure;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;

class ArticleEngagementResource extends Resource
{
    protected static ?string $model = ArticleEngagement::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static string | \UnitEnum | null $navigationGroup = 'Engagements';

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $query->orderBy('created_at', 'desc');
        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make([
                    Hidden::make('user_id')
                        ->default(auth()->id()),

                    Select::make('users')
                        ->label('Users')
                        ->multiple()
                        ->relationship('users', 'name')
                        ->getSearchResultsUsing(function (string $search) {
                            return User::query()
                                ->where('name', 'like', "%{$search}%")
                                ->where('for_engagement', true)
                                ->where('status', User::STATUS_ACTIVE)
                                ->limit(50)
                                ->pluck('name', 'id');
                        })
                        ->getOptionLabelFromRecordUsing(fn ($record) => 'ID:'. $record->id.' ('.$record->name.')')
                        ->helperText('If multiple users selected, they will gap by random minutes(1min-120hours) one by one to do action.')
                        ->searchable(),

                    Select::make('article_id')
                        ->label('Article')
                        ->relationship('article', 'title')
                        ->getSearchResultsUsing(function (string $search) {
                            $articles = Article::query()
                                ->where(function ($query) use ($search) {
                                    if (is_numeric($search)) {
                                        $query->where('id', $search);
                                    }
                                    $query->orWhere('title', 'like', "%{$search}%");
                                })
                                ->limit(50)
                                ->get();
                            return $articles->mapWithKeys(function ($article) {
                                return [$article->id => 'ID:' . $article->id . ' (' . $article->title . ')'];
                            })->toArray();
                        })
                        ->getOptionLabelFromRecordUsing(fn ($record) => 'ID:'. $record->id.' ('.$record->title.')')
                        ->searchable()
                        ->required(),

                    Select::make('action')
                        ->options([
                            'like' => 'Like',
                            'comment' => 'Comment',
                        ])
                        ->reactive()
                        ->required(),

                    Textarea::make('comment')
                        ->nullable()
                        ->visible(fn (Get $get) => $get('action') === 'comment'),

                    DateTimePicker::make('scheduled_at')
                        ->label('Scheduled At')
                        ->helperText('If empty, will be added immediately.')
                        ->nullable(),

                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
        ->columns([
            TextColumn::make('id')
                ->sortable()
                ->searchable(),

            TextColumn::make('created_at')
                ->sortable()
                ->dateTime(),

            TextColumn::make('users.name')
                ->label('User')
                ->searchable(),

            TextColumn::make('action')
                ->searchable(),

            TextColumn::make('article.title')
                ->label('Article')
                ->searchable(),

        ])
        ->filters([
            Filter::make('scheduled_at')
                ->schema([
                    DatePicker::make('scheduled_from')
                        ->placeholder(fn ($state): string => 'Dec 18, ' . now()->subYear()->format('Y')),
                    DatePicker::make('scheduled_until')
                        ->placeholder(fn ($state): string => now()->format('M d, Y')),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    return $query
                        ->when(
                            $data['scheduled_from'],
                            fn (Builder $query, $date): Builder => $query->whereDate('scheduled_at', '>=', $date),
                        )
                        ->when(
                            $data['scheduled_until'],
                            fn (Builder $query, $date): Builder => $query->whereDate('scheduled_at', '<=', $date),
                        );
                }),

            Filter::make('created_at')
                ->schema([
                    DatePicker::make('created_from')
                        ->placeholder(fn ($state): string => 'Dec 18, ' . now()->subYear()->format('Y')),
                    DatePicker::make('created_until')
                        ->placeholder(fn ($state): string => now()->format('M d, Y')),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    return $query
                        ->when(
                            $data['created_from'],
                            fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                        )
                        ->when(
                            $data['created_until'],
                            fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                        );
                }),
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
            'index' => ListArticleEngagements::route('/'),
            'create' => CreateArticleEngagement::route('/create'),
            'edit' => EditArticleEngagement::route('/{record}/edit'),
        ];
    }
}
