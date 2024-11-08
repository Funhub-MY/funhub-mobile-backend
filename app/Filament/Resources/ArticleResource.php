<?php

namespace App\Filament\Resources;

use Closure;
use Filament\Forms;
use App\Models\User;
use App\Models\View;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Tables;
use App\Models\Article;
use App\Models\Location;
use App\Models\ArticleTag;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Filament\Resources\Form;
use Filament\Resources\Table;
use App\Models\ArticleCategory;
use Filament\Resources\Resource;
use Filament\Forms\FormsComponent;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\DatePicker;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\ArticleResource\Pages;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\ArticleResource\RelationManagers;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use pxlrbt\FilamentExcel\Columns\Column;
use pxlrbt\FilamentExcel\Exports\ExcelExport;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tapp\FilamentAuditing\RelationManagers\AuditsRelationManager;
use App\Filament\Resources\LocationRelationManagerResource\RelationManagers\LocationRelationManager;
use Illuminate\Support\Facades\Storage;

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
                                ->enableReordering()
                                ->appendFiles()
                                ->hidden(fn (Closure $get) => $get('type') !== 'multimedia')
                                ->rules('image'),

                            //  video upload
                            // image upload for video thumbnail
                            FileUpload::make('video_thumbnail')
                                ->label('Video Thumbnail')
                                ->helperText('This image will be used as the thumbnail for the video')
                                ->columnSpan('full')
                                ->disk(function () {
                                    if (config('filesystems.default') === 's3') {
                                        return 's3_public';
                                    }
                                })
                                ->directory('filament-article-uploads')
                                ->acceptedFileTypes(['image/*'])
                                ->rules('image')
                                ->hidden(fn (Closure $get) => $get('type') !== 'video')
                                ->getUploadedFileUrlUsing(function ($file) {
                                    $disk = config('filesystems.default');

                                    if (config('filesystems.default') === 's3') {
                                        $disk = 's3_public';
                                        Log::info('Disk: '. $disk);
                                    }
                                    Log::info(Storage::disk($disk)->url($file));
                                    return Storage::disk($disk)->url($file);
                                }),
                            FileUpload::make('video')
                                ->label('Video File')
                                ->helperText('One Video Only, Maximum file size: '. (config('app.max_size_per_video_kb') / 1024 / 1024). ' MB. Allowable types: mp4, mov')
                                ->columnSpan('full')
                                ->disk(function () {
                                    if (config('filesystems.default') === 's3') {
                                        return 's3_public';
                                    }
                                })
                                ->directory('filament-article-uploads')
                                ->acceptedFileTypes(['video/*'])
                                ->hidden(fn (Closure $get) => $get('type') !== 'video')
                                ->rules('mimes:m4v,mp4,mov|max:'.config('app.max_size_per_video_kb'))
                                ->getUploadedFileUrlUsing(function ($file) {
                                    $disk = config('filesystems.default');

                                    if (config('filesystems.default') === 's3') {
                                        $disk = 's3_public';
                                        Log::info('Disk: '. $disk);
                                    }
                                    Log::info(Storage::disk($disk)->url($file));
                                    return Storage::disk($disk)->url($file);
                                }),
                        ])->columnSpan('full')
                    ])
                    ->columnSpan(['lg' => 2]),

                Forms\Components\Group::make()
                    ->schema([
                        Section::make('Author')->schema([
                            Forms\Components\Select::make('source')
                            ->options([
                                'backend' => 'Backend',
                                'mobile' => 'Mobile App'
                            ])
                            ->helperText('Source will determine display format in app. If backend, will display as media partner style, if mobile, will display similar to mobile post design.')
                            ->default('backend'),

                            Forms\Components\Select::make('user_id')
                                ->label('Author')
                                ->searchable()
                                ->helperText('System will default to Admin user if not selected')
                                ->required()
                                ->default(fn () => auth()->id())
                                ->relationship('user', 'name')
                                ->getOptionLabelFromRecordUsing(function ($record) {
                                    return $record->name ?? 'Unknown Author';
                                }),

                        ]),
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

                            Forms\Components\Select::make('visibility')
                                ->default(Article::VISIBILITY_PUBLIC)
                                ->helperText('If articles are private, only visible if you followed the author. Location ratings will not be public as well.')
                                ->label('Visibility')
                                ->options([
                                    Article::VISIBILITY_PRIVATE => 'Private',
                                    Article::VISIBILITY_PUBLIC => 'Public',
                                ]),

                            Toggle::make('hidden_from_home')
                                ->label('Hide from Home?')
                                ->helperText('Article will not showed in Recommendations. Whitelist user to bypass.')
                                ->default(false)
                                ->afterStateUpdated(function ($record) {
                                    if ($record) {
                                        try {
                                            $record->searchable();
                                            Log::info('[ArticleResource] Hidden from home toggle button, article added/removed to search index(algolia)', [
                                                'article_id' => $record->id,
                                            ]);
                                        } catch (\Exception $e) {
                                            Log::error('[ArticleResource] Hidden from Home Toggle Error', [
                                                'article_id' => $record->id,
                                                'error' => $e->getMessage()
                                            ]);
                                        }
                                    }
                                }),
                            // Toggle::make('pinned_recommended')
                            //     ->label('Pin to top in Recommendations?')
                            //     ->helperText('Article will be pinned to top in Recommendations.')
                            //     ->default(false),
                            Forms\Components\DateTimePicker::make('published_at')
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
                                ->relationship('tags', 'name')
                                ->createOptionForm([
                                    Forms\Components\TextInput::make('name')
                                        ->required()
                                        ->placeholder('Tag name'),
                                   // hidden user id is logged in user
                                   Forms\Components\Hidden::make('user_id')
                                    ->default(fn () => auth()->id()),
                                ])
                                // search
                                ->searchable()
                                ->getSearchResultsUsing(function (string $search) {
                                    // search by name in article tags and unique name.
                                    $tags  = DB::table('article_tags')
                                    ->select(DB::raw('MAX(id) as id'),'name')
                                    ->where('name', 'like', "%{$search}%")
                                    ->groupBy('name')
                                    ->pluck('name', 'id')
                                    ->toArray();
                                    return $tags;
                                })
                                ->getOptionLabelUsing(fn ($value): ?string => ArticleTag::find($value)?->name)
                                ->multiple()
                                ->placeholder('Select tags...'),
                        ]),

                        Forms\Components\Section::make('Location')->schema([
                            Forms\Components\Select::make('locations')
                                ->label('')
                                ->options(Location::all()->pluck('name', 'id')->toArray())
                                // search
                                ->searchable()
                                ->placeholder('Select location...'),
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
                    ->url(fn ($record) => route('filament.resources.users.view', $record->user))
                    ->openUrlInNewTab()
                    ->sortable()
                    ->searchable(),
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

                Tables\Columns\BadgeColumn::make('type')
                    ->enum(Article::TYPE)
                    ->colors([
                        'primary' => 'video',
                        'secondary' => 'multimedia',
                    ])
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('hidden_from_home')
                    ->label('Hidden (Home)')
                    ->enum([
                        0 => 'No',
                        1 => 'Yes',
                    ])
                    ->colors([
                        'secondary' => 0,
                        'success' => 1,
                    ])
                    ->sortable()
                    ->searchable(),
                Tables\Columns\BadgeColumn::make('pinned_recommended')
                    ->label('Pinned (Recommended)')
                    ->enum([
                        0 => 'No',
                        1 => 'Yes',
                    ])
                    ->colors([
                        'secondary' => 0,
                        'success' => 1,
                    ])
                    ->sortable()
                    ->searchable(),
                Tables\Columns\BadgeColumn::make('visibility')
                    ->label('Visibility')
                    ->enum([
                        Article::VISIBILITY_PRIVATE => 'Private',
                        Article::VISIBILITY_PUBLIC => 'Public',
                    ])
                    ->colors([
                        'secondary' => Article::VISIBILITY_PRIVATE,
                        'success' => Article::VISIBILITY_PUBLIC,
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
                Tables\Columns\TextColumn::make('bookmarks_count')
                    ->label('Bookmarks')
                    ->counts('bookmarks')
                    ->sortable(),
                Tables\Columns\TextColumn::make('shares_count')
                    ->label('Shares')
                    ->counts('shares')
                    ->sortable(),

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

                Tables\Columns\TextColumn::make('organic_views_count')
                    ->label('Organic Views')
                    ->getStateUsing(function (Article $record) {
                        if ($record->status === Article::STATUS_PUBLISHED) {
                            $num_organic_views = View::query()
                                ->where('viewable_id', $record->id)
                                ->where('viewable_type', Article::class)
                                ->where('is_system_generated', false)
                                ->count();
                        } else {
                            $num_organic_views = 0;
                        }
                        return $num_organic_views;
                    })
                    ->sortable(query: function (Builder $query, string $direction) {
                        $query->withCount(['views as organic_views_count' => function ($query) {
                            $query->where('is_system_generated', false);
                        }])->orderBy('organic_views_count', $direction);
                    }),

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
                // filter by hidden_from_home
                Tables\Filters\SelectFilter::make('hidden_from_home')
                    ->label('Hidden from home?')
                    ->options([
                        0 => 'No',
                        1 => 'Yes',
                    ])
                    ->placeholder('All'),
                Tables\Filters\SelectFilter::make('pinned_recommended')
                    ->label('Pinned (Recommended)')
                    ->options([
                        0 => 'No',
                        1 => 'Yes',
                    ])
                    ->placeholder('All'),

                // filter by has video
                Tables\Filters\SelectFilter::make('type')
                    ->label('Has Video?')
                    ->options([
                        'video' => 'Yes',
                        'multimedia' => 'No',
                    ])
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
            ->defaultSort('created_at', 'desc')
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
                })->requiresConfirmation()->deselectRecordsAfterCompletion(),
                Tables\Actions\BulkAction::make('Change to Draft')
                ->action(function (Collection $records) {
                    $records->each(function (Article $record) {
                        $record->update(['status' => 0]);
                    });
                })->requiresConfirmation()->deselectRecordsAfterCompletion(),
                Tables\Actions\BulkAction::make('Move to Archived')
                ->action(function (Collection $records) {
                    $records->each(function (Article $record) {
                        $record->update(['status' => 2]);
                    });
                })->requiresConfirmation()->deselectRecordsAfterCompletion(),
                // table bulkaction to mark hidden from home toggle
                Tables\Actions\BulkAction::make('toggle_hidden_from_home')
                ->label('Toggle Home Hidden/Visible')
                ->form([
                    Toggle::make('hidden_from_home')
                        ->label('Hide from Home?')
                        ->default(true)
                ])
                ->action(function(array $data, $livewire){
                    if (count($livewire->selectedTableRecords) > 0) {
                        Article::whereIn('id', $livewire->selectedTableRecords)
                            ->update(['hidden_from_home' => $data['hidden_from_home']]);

                       try {
                         // trigger searcheable to reindex
                         foreach ($livewire->selectedTableRecords as $article_id) {
                            $article = Article::find($article_id);
                            if ($article) {
                                $article->searchable();
                                Log::info('[ArticleResource] Hidden from Home Bulk Action, article added.removed to search index(algolia)', [
                                    'article_id' => $article_id,
                                ]);
                            }
                        }
                       } catch (\Exception $e) {
                           Log::error('[ArticleResource] Hidden from Home Bulk Action Error', [
                               'error' => $e->getMessage()
                           ]);
                       }
                    }
                })->requiresConfirmation()->deselectRecordsAfterCompletion(),

                // toggle pinned_recommended
                Tables\Actions\BulkAction::make('toggle_pinned_recommended')
                ->label('Toggle Pinned Recommended')
                ->form([
                    Toggle::make('pinned_recommended')
                        ->label('Pin to Recommended')
                        ->default(false)
                ])
                ->action(function(array $data, $livewire){
                    if (count($livewire->selectedTableRecords) > 0) {
                        Article::whereIn('id', $livewire->selectedTableRecords)
                            ->update(['pinned_recommended' => $data['pinned_recommended']]);
                    }
                })->requiresConfirmation()->deselectRecordsAfterCompletion(),
				ExportBulkAction::make()
					->exports([
						ExcelExport::make()
							->label('Export Articles Categories')
							->withColumns([
								Column::make('id')->heading('article_id'),
								Column::make('title')->heading('article_title'),
								Column::make('categories.name')
									->heading('category_names')
									->getStateUsing(fn($record) => $record->categories->pluck('name')->join(',')),
							])
							->withFilename(fn($resource) => $resource::getModelLabel() . '-' . date('Y-m-d'))
							->withWriterType(\Maatwebsite\Excel\Excel::CSV)
					]),

            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\CommentsRelationManager::class,
            RelationManagers\InteractionsRelationManager::class,
            RelationManagers\LocationsRelationManager::class,
            RelationManagers\MerchantOffersRelationManager::class,
            AuditsRelationManager::class,
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
