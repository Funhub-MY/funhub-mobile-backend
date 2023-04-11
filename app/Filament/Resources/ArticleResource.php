<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ArticleResource\Pages;
use App\Filament\Resources\ArticleResource\RelationManagers;
use App\Models\Article;
use App\Models\ArticleCategory;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;

class ArticleResource extends Resource
{
    protected static ?string $model = Article::class;

    protected static ?string $navigationIcon = 'heroicon-o-collection';

    protected static ?string $navigationGroup = 'Articles';

    protected static ?int $navigationSort = 1;


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Card::make()->schema([
                            Forms\Components\Hidden::make('user_id')
                                ->default(fn () => auth()->id()),
                            Forms\Components\TextInput::make('title')
                                ->required()
                                ->lazy()
                                ->afterStateUpdated(fn (string $context, $state, callable $set) => $context === 'create' ? $set('slug', Str::slug($state)) : null),

                            Forms\Components\TextInput::make('slug')
                                ->disabled()
                                ->required()
                                ->helperText('Filled automatically when you fill title')
                                ->unique(Article::class, 'slug', ignoreRecord: true),
                            Forms\Components\Hidden::make('type')
                                ->default(Article::TYPE[0])
                                ->required(),
                            Forms\Components\RichEditor::make('body')
                                ->required()
                                ->placeholder('Write something...')
                                ->columnSpan('full'),
                        ])->columns(2),

                        Forms\Components\Section::make('Gallery')->schema([
                            // Forms\Components\SpatieMediaLibraryFileUpload::make('cover_image')
                            //     ->label('Cover Image')
                            //     ->collection('article_cover')
                            //     ->customProperties(['is_cover' => true])
                            //     ->columnSpan('full')
                            //     ->maxFiles(1)
                            //     ->rules('image'),
                            // multiple images
                            Forms\Components\SpatieMediaLibraryFileUpload::make('gallery')
                                ->label('Images')
                                ->multiple()
                                ->collection(Article::MEDIA_COLLECTION_NAME)
                                ->columnSpan('full')
                                ->customProperties(['is_cover' => false])
                                ->maxFiles(10)
                                ->rules('image'),
                        ])->columnSpan('full')
                    ])
                    ->columnSpan(['lg' => 2]),

                Forms\Components\Group::make()
                    ->schema([
                        Section::make('Language')->schema([
                            Forms\Components\Select::make('lang')
                                ->options([
                                    'en' => 'English',
                                    'zh' => 'Chinese',
                                ])
                                // hide label
                                ->label('')
                                ->default('en')
                                ->required()
                        ])->columnSpan('Language'),
                        
                        Forms\Components\Section::make('Status')->schema([
                            Forms\Components\Select::make('status')
                                ->options(Article::STATUS)->default(0),
                            Forms\Components\DatePicker::make('published_at')
                                ->label('Publish At')
                                // set rule to date and must be a future date
                                ->rules('date|after_or_equal:today')
                                ->helperText('If you choose a future date, the article will be published at that date.')
                                ->default(now())
                                ->required()
                        ])->columnSpan('Status'),

                        Forms\Components\Section::make('Categories')->schema([
                            Forms\Components\Select::make('categories')
                                ->label('')
                                ->relationship('categories', 'name')->createOptionForm([
                                    Forms\Components\TextInput::make('name')
                                        ->required()
                                        ->placeholder('Category name'),
                                    // slug
                                    Forms\Components\TextInput::make('slug')
                                        ->required()
                                        ->placeholder('Category slug')
                                        ->unique(ArticleCategory::class, 'slug', ignoreRecord: true),
                                    Forms\Components\RichEditor::make('description')
                                        ->placeholder('Category description'),
                                    // is_featured
                                    Forms\Components\Toggle::make('is_featured')
                                        ->label('Featured on Home Page?')
                                        ->default(false),
                                    // hidden user id is logged in user
                                    Forms\Components\Hidden::make('user_id')
                                        ->default(fn () => auth()->id()),
                                ])
                                ->multiple()
                                ->preload()
                                ->placeholder('Select categories...'),
                        ]),

                        Forms\Components\Section::make('Tags')->schema([
                            Forms\Components\Select::make('tags')
                                ->label('')
                                ->relationship('tags', 'name')->createOptionForm([
                                    Forms\Components\TextInput::make('name')
                                        ->required()
                                        ->placeholder('Tag name'),
                                   // hidden user id is logged in user
                                   Forms\Components\Hidden::make('user_id')
                                    ->default(fn () => auth()->id()),
                                ])
                                ->multiple()
                                ->preload()
                                ->placeholder('Select tags...'),
                        ]),

                    ])
                    ->columnSpan(['lg' => 1]),
            ])
            ->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // date created at
                Tables\Columns\TextColumn::make('created_at')->dateTime('d/m/Y H:ia')
                    ->sortable()
                    ->label('Created At')
                    ->searchable(),
                Tables\Columns\TextColumn::make('user.name')->label('Created By')
                    ->sortable()->searchable(),
                Tables\Columns\TextColumn::make('title')
                    ->sortable()
                    ->searchable(),
                    Tables\Columns\BadgeColumn::make('status')
                    ->enum(Article::STATUS)
                    ->colors([
                        'secondary' => 0,
                        'success' => 1,
                    ])
                    ->sortable()
                    ->searchable(),
                // Tables\Columns\TextColumn::make('excerpt')
                //     ->sortable()
                //     ->searchable()->wrap(),
                Tables\Columns\TextColumn::make('type')
                    ->enum(Article::TYPE)
                    ->sortable()
                    ->searchable(),
        
                Tables\Columns\TextColumn::make('published_at')->dateTime('d/m/Y')
                    ->sortable()
                    ->label('Publish At')
                    ->searchable(),
             
            ])
            ->filters([
                // filter by user
                Tables\Filters\SelectFilter::make('user_id')
                    ->label('Created By')
                    ->searchable()
                    ->options(fn () => Article::query()->withoutGlobalScope(SoftDeletingScope::class)->get()->pluck('user.name', 'user_id'))
                    ->placeholder('All'),
                // filter by status
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(Article::STATUS)
                    ->placeholder('All'),
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
            RelationManagers\CommentsRelationManager::class,
            RelationManagers\InteractionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListArticles::route('/'),
            'create' => Pages\CreateArticle::route('/create'),
            'edit' => Pages\EditArticle::route('/{record}/edit'),
        ];
    }
}
