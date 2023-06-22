<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ArticleResource\Pages;
use App\Filament\Resources\ArticleResource\RelationManagers;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\FormsComponent;
use Filament\Tables\Filters\Filter;

class ArticleResource extends Resource
{
    protected static ?string $model = Article::class;

    protected static ?string $navigationIcon = 'heroicon-o-collection';

    protected static ?string $navigationGroup = 'Articles';

    protected static ?int $navigationSort = 1;
    
    protected function getTableQuery(): Builder
    {
        return Article::query();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Card::make()->schema([
                            Forms\Components\Hidden::make('user_id')
                                ->default(fn () => auth()->id()),
                            
                            // default set source to backend because we need to flag it for flutter side to determine to use html or not
                            Forms\Components\Hidden::make('source')
                                ->default('backend'),

                            Forms\Components\TextInput::make('title')
                                ->required()
                                ->lazy(),

                            Forms\Components\TextInput::make('slug')
                                ->required()
                                ->placeholder('eg. my-article-slug')
                                ->helperText('The slug is used to generate the URL of the article.')
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
                            // select type of article
                            Forms\Components\Select::make('type')
                                ->label('Type')
                                ->options([
                                    'multimedia' => 'Images Article',
                                    'video' => 'Video Article',
                                ])
                                ->reactive()
                                ->required()
                                ->rules('required')
                                ->helperText('Different type of articles will be displayed differently in user view')
                                ->columnSpan('full'),

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
                                // disk is s3_public 
                                ->disk(function () {
                                    if (config('filesystems.default') === 's3') {
                                        return 's3_public';
                                    }
                                })
                                ->acceptedFileTypes(['image/*'])
                                ->maxFiles(20)
                                ->hidden(fn (Closure $get) => $get('type') !== 'multimedia')
                                ->rules('image'),

                            //  video upload
                            // image upload for video thumbnail
                            Forms\Components\SpatieMediaLibraryFileUpload::make('video_thumbnail')
                                ->label('Video Thumbnail')
                                ->helperText('This image will be used as the thumbnail for the video')
                                ->collection(Article::MEDIA_COLLECTION_NAME)
                                ->columnSpan('full')
                                ->disk(function () {
                                    if (config('filesystems.default') === 's3') {
                                        return 's3_public';
                                    }
                                })
                                ->customProperties(['is_cover' => true])
                                ->acceptedFileTypes(['image/*'])
                                ->maxFiles(1)
                                ->hidden(fn (Closure $get) => $get('type') !== 'video')
                                ->rules('image'),

                            Forms\Components\SpatieMediaLibraryFileUpload::make('video')
                                ->label('Video File')
                                ->collection(Article::MEDIA_COLLECTION_NAME)
                                ->columnSpan('full')
                                ->disk(function () {
                                    if (config('filesystems.default') === 's3') {
                                        return 's3_public';
                                    }
                                })
                                ->acceptedFileTypes(['video/*'])
                                ->helperText('One Video Only, Maximum file size: '. (config('app.max_size_per_video_kb') / 1024 / 1024). ' MB. Allowable types: mp4, mov')
                                ->hidden(fn (Closure $get) => $get('type') !== 'video')
                                ->rules('mimes:m4v,mp4,mov|max:'.config('app.max_size_per_video_kb')),
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
                                               
                                ->helperText('If you choose a future date, the article will be published at that date.')
                                ->default(now())
                        ])->columnSpan('Status'),

                        Forms\Components\Section::make('Categories')->schema([
                            Forms\Components\Select::make('categories')
                                ->label('')
                                ->relationship('categories', 'name', fn (Builder $query) => $query->whereNull('parent_id'))
                                // ->createOptionForm([
                                //     Forms\Components\TextInput::make('name')
                                //         ->required()
                                //         ->placeholder('Category name'),
                                //     // slug
                                //     Forms\Components\TextInput::make('slug')
                                //         ->required()
                                //         ->placeholder('Category slug')
                                //         ->unique(ArticleCategory::class, 'slug', ignoreRecord: true),
                                //     Forms\Components\RichEditor::make('description')
                                //         ->placeholder('Category description'),
                                //     // is_featured
                                //     Forms\Components\Toggle::make('is_featured')
                                //         ->label('Featured on Home Page?')
                                //         ->default(false),
                                //     // hidden user id is logged in user
                                //     Forms\Components\Hidden::make('user_id')
                                //         ->default(fn () => auth()->id()),
                                // ])
                                ->multiple()
                                ->preload()
                                ->reactive()
                                ->placeholder('Select categories...'),
                        ]),

                        // sub category using parent_id
                        // WHY using normal select instead relationshio select? see EditArticle due to subCategories are self joined and eloquent has problem sync self joined tables
                        Forms\Components\Section::make('Sub Categories')->schema([
                            Select::make('sub_categories')
                                ->label('')
                                ->multiple()
                                ->options(ArticleCategory::whereNotNull('parent_id')->get()->pluck('name', 'id')->toArray())

                            // Forms\Components\Select::make('sub_categories')
                            //     ->label('')
                            //     ->relationship('subCategories', 'name', fn (Builder $query) => $query->whereNotNull('parent_id'))->createOptionForm([
                            //         // select parent
                            //         Select::make('parent_id')
                            //             ->label('Parent Category')
                            //             ->options(ArticleCategory::whereNull('parent_id')->get()->pluck('name', 'id')->toArray())
                            //             ->required()
                            //             ->rules('required'),

                            //         Forms\Components\TextInput::make('name')
                            //             ->required()
                            //             ->placeholder('Sub Category name'),
                            //         // slug
                            //         Forms\Components\TextInput::make('slug')
                            //             ->required()
                            //             ->placeholder('Sub Category slug')
                            //             ->unique(ArticleCategory::class, 'slug', ignoreRecord: true),
                            //         Forms\Components\RichEditor::make('description')
                            //             ->placeholder('Sub Category description'),
                            //         // is_featured
                            //         Forms\Components\Toggle::make('is_featured')
                            //             ->label('Featured on Home Page?')
                            //             ->default(false),
                            //         // hidden user id is logged in user
                            //         Forms\Components\Hidden::make('user_id')
                            //             ->default(fn () => auth()->id()),
                            //     ])
                            //     ->multiple()
                            //     ->preload()
                            //     ->placeholder('Select sub categories...'),
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
                                // search
                                ->searchable()
                                ->multiple()
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
                // id column
                Tables\Columns\TextColumn::make('id')->sortable()->searchable(),
                // date created at
                Tables\Columns\TextColumn::make('created_at')->dateTime('d/m/Y H:ia')
                    ->sortable()
                    ->label('Created At')
                    ->searchable(),
                Tables\Columns\TextColumn::make('user.name')->label('Created By')
                    ->limit(30)
                    ->sortable()->searchable(),
                // change status directly togglecolumn
                Tables\Columns\ToggleColumn::make('status')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('title')
                    ->sortable()
                    ->searchable()
                    ->limit(50),
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
                // Tables\Columns\TextColumn::make('type')
                //     ->enum(Article::TYPE)
                //     ->sortable()
                //     ->searchable(),
        
                // likes count
                Tables\Columns\TextColumn::make('likes_count')
                    ->sortable()
                    ->counts('likes')
                    ->label('Likes'),

                // comments count
                Tables\Columns\TextColumn::make('comments_count')
                    ->counts('comments')
                    ->sortable()
                    ->label('Comments'),

                // view count
                Tables\Columns\TextColumn::make('views_count')
                    ->counts('views')
                    ->sortable()
                    ->label('Views'),

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
                    ->options(fn () => User::select('id', 'name')->get()->pluck('name', 'id')->toArray())
                    ->placeholder('All'),
                // filter by status
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(Article::STATUS)
                    ->placeholder('All'),
                // filter by ArticleCategory
                Tables\Filters\SelectFilter::make('categories')
                    ->label('Categories')
                    ->relationship('categories', 'name')
                    ->multiple()
                    ->searchable()
                    ->options(fn () => ArticleCategory::select('id', 'name')->get()->pluck('name', 'id')->toArray())
                    ->placeholder('All'),
                // filter by Article created_at date range
                Filter::make('created_at')
                ->form([
                    DatePicker::make('created_from'),
                    DatePicker::make('created_until'),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    return $query
                        ->when(
                            $data['created_from'],
                            fn(Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                        )
                        ->when(
                            $data['created_until'],
                            fn(Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                        );
                })
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
                // bulk change status
                Tables\Actions\BulkAction::make('Change to Published')
                ->action(function (Collection $records) {
                    $records->each(function (Article $record) {
                        $record->update([
                            'status' => 1,
                            'published_at' => now()
                        ]);
                    });
                })->requiresConfirmation(),
                Tables\Actions\BulkAction::make('Change to Draft')
                ->action(function (Collection $records) {
                    $records->each(function (Article $record) {
                        $record->update(['status' => 0]);
                    });
                })->requiresConfirmation(),
                Tables\Actions\BulkAction::make('Move to Archived')
                ->action(function (Collection $records) {
                    $records->each(function (Article $record) {
                        $record->update(['status' => 2]);
                    });
                })->requiresConfirmation()
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
