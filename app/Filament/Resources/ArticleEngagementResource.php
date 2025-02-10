<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ArticleEngagementResource\Pages;
use App\Filament\Resources\ArticleEngagementResource\RelationManagers;
use App\Models\ArticleEngagement;
use App\Models\User;
use Closure;
use Filament\Forms;
use Filament\Forms\Components\Card;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
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

    protected static ?string $navigationIcon = 'heroicon-o-collection';

    protected static ?string $navigationGroup = 'Engagements';

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $query->orderBy('created_at', 'desc');
        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Card::make([
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
                                ->where('status', User::STATUS_ACTIVE) // Add your custom where condition here
                                ->limit(50)
                                ->pluck('name', 'id');
                        })
                        ->getOptionLabelFromRecordUsing(fn ($record) => 'ID:'. $record->id.' ('.$record->name.')')
                        ->helperText('If multiple users selected, they will gap by random minutes(1min-120hours) one by one to do action.')
                        ->searchable(),

                    Select::make('article_id')
                        ->label('Article')
                        ->relationship('article', 'title')
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
                        ->visible(fn (Closure $get) => $get('action') === 'comment'),

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
            Tables\Columns\TextColumn::make('id')
                ->sortable()
                ->searchable(),

            Tables\Columns\TextColumn::make('created_at')
                ->sortable()
                ->dateTime(),

            Tables\Columns\TextColumn::make('users.name')
                ->label('User')
                ->searchable(),

            Tables\Columns\TextColumn::make('action')
                ->searchable(),

            Tables\Columns\TextColumn::make('article.title')
                ->label('Article')
                ->searchable(),

        ])
        ->filters([
            Tables\Filters\Filter::make('scheduled_at')
                ->form([
                    Forms\Components\DatePicker::make('scheduled_from')
                        ->placeholder(fn ($state): string => 'Dec 18, ' . now()->subYear()->format('Y')),
                    Forms\Components\DatePicker::make('scheduled_until')
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

            Tables\Filters\Filter::make('created_at')
                ->form([
                    Forms\Components\DatePicker::make('created_from')
                        ->placeholder(fn ($state): string => 'Dec 18, ' . now()->subYear()->format('Y')),
                    Forms\Components\DatePicker::make('created_until')
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
            'index' => Pages\ListArticleEngagements::route('/'),
            'create' => Pages\CreateArticleEngagement::route('/create'),
            'edit' => Pages\EditArticleEngagement::route('/{record}/edit'),
        ];
    }
}
