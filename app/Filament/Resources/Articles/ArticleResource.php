<?php

namespace App\Filament\Resources\Articles;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\RichEditor;
use Filament\Schemas\Components\Utilities\Get;
use Exception;
use Filament\Forms\Components\DateTimePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\BulkAction;
use Maatwebsite\Excel\Excel;
use App\Filament\Resources\Articles\RelationManagers\CommentsRelationManager;
use App\Filament\Resources\Articles\RelationManagers\InteractionsRelationManager;
use App\Filament\Resources\Articles\RelationManagers\LocationsRelationManager;
use App\Filament\Resources\Articles\RelationManagers\MerchantOffersRelationManager;
use App\Filament\Resources\Articles\Pages\ListArticles;
use App\Filament\Resources\Articles\Pages\CreateArticle;
use App\Filament\Resources\Articles\Pages\EditArticle;
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
use Filament\Tables\Columns\TagsColumn;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Filament\Tables\Table;
use App\Models\ArticleCategory;
use Filament\Resources\Resource;
use Filament\Forms\FormsComponent;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
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
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;
use Filament\Forms\Components\Placeholder;

class ArticleResource extends Resource
{
    protected static ?string $model = Article::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static string | \UnitEnum | null $navigationGroup = 'Articles';

    protected static ?int $navigationSort = 1;


    protected function getTableQuery(): Builder
    {
        return Article::query();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Group::make()
                    ->schema([
                        Section::make()->schema([

                            TextInput::make('title')
                                ->required()
                                ->lazy(),

                            TextInput::make('slug')
                                ->required()
                                ->placeholder('eg. my-article-slug')
                                ->helperText('The slug is used to generate the URL of the article.')
                                ->unique(Article::class, 'slug', ignoreRecord: true),

                            Hidden::make('type')
                                ->default(Article::TYPE[0])
                                ->required(),

                            RichEditor::make('body')
                                ->required()
                                ->placeholder('Write something...')
                                ->columnSpan('full'),
                        ])->columns(2),

                        Section::make('Gallery')->schema([
                            // select type of article
                            Select::make('type')
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
                            SpatieMediaLibraryFileUpload::make('gallery')
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
                                ->hidden(fn (Get $get) => $get('type') !== 'multimedia')
                                ->rules('image'),

                            //  video upload
                            // image upload for video thumbnail
                            FileUpload::make('video_thumbnail')
                                ->label('Video Thumbnail')
                                ->helperText('This image will be used as the thumbnail for the video (Max file size 4MB)')
                                ->columnSpan('full')
                                ->disk(function () {
                                    if (config('filesystems.default') === 's3') {
                                        return 's3_public';
                                    }
                                })
                                ->directory('filament-article-uploads')
                                ->acceptedFileTypes(['image/*'])
                                ->rules('image')
								->required()
								->maxSize(4096)
                                ->hidden(fn (Get $get) => $get('type') !== 'video')
                                ->getUploadedFileUsing(function ($component, $file, $storedFileNames) {
                                    $disk = config('filesystems.default');

                                    if (config('filesystems.default') === 's3') {
                                        $disk = 's3_public';
                                        Log::info('Disk: '. $disk);
                                    }
                                    
                                    $storage = Storage::disk($disk);
                                    $url = $storage->url($file);
                                    Log::info($url);
                                    
                                    return [
                                        'name' => basename($file),
                                        'size' => $storage->exists($file) ? $storage->size($file) : 0,
                                        'type' => $storage->exists($file) ? $storage->mimeType($file) : null,
                                        'url' => $url,
                                    ];
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
                                ->hidden(fn (Get $get) => $get('type') !== 'video')
                                ->rules('mimes:m4v,mp4,mov|max:'.config('app.max_size_per_video_kb'))
								->required()
								->getUploadedFileUsing(function ($component, $file, $storedFileNames) {
                                    $disk = config('filesystems.default');

                                    if (config('filesystems.default') === 's3') {
                                        $disk = 's3_public';
                                        Log::info('Disk: '. $disk);
                                    }
                                    
                                    $storage = Storage::disk($disk);
                                    $url = $storage->url($file);
                                    Log::info($url);
                                    
                                    return [
                                        'name' => basename($file),
                                        'size' => $storage->exists($file) ? $storage->size($file) : 0,
                                        'type' => $storage->exists($file) ? $storage->mimeType($file) : null,
                                        'url' => $url,
                                    ];
                                }),
                        ])->columnSpan('full')
                    ])
                    ->columnSpan(['lg' => 2]),

                Group::make()
                    ->schema([
                        Section::make('Author')->schema([
                            Select::make('source')
                            ->options([
                                'backend' => 'Backend',
                                'mobile' => 'Mobile App'
                            ])
                            ->helperText('Source will determine display format in app. If backend, will display as media partner style, if mobile, will display similar to mobile post design.')
                            ->default('backend'),

                            Select::make('user_id')
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
                            Select::make('lang')
                                ->options([
                                    'en' => 'English',
                                    'zh' => 'Chinese',
                                ])
                                // hide label
                                ->label('')
                                ->default('en')
                                ->required()
                        ])->columnSpan('Language'),

                        Section::make('Status')->schema([
                            Select::make('status')
                                ->options(Article::STATUS)->default(0),

                            // available_for_web
                            Toggle::make('available_for_web')
                                ->label('Available for Web')
                                ->helperText('If enabled, this article will be shown in Funhub Web. Max 5 articles web at a time.')
                                ->default(false)
                                ->afterStateUpdated(function ($state, $record, $set) {
                                    if ($state) { // Only check when enabling
                                        $currentWebAvailable = Article::where('available_for_web', true)
                                            ->published()
                                            ->where('visibility', Article::VISIBILITY_PUBLIC)
                                            ->when($record, fn($query) => $query->where('id', '!=', $record->id))
                                            ->count();
                                            
                                        if ($currentWebAvailable >= 5) {
                                            Notification::make()
                                                ->title('Maximum limit reached')
                                                ->body('You can only have 5 articles  published, public, available for web at a time.')
                                                ->danger()
                                                ->send();
                                                
                                            $set('available_for_web', false);
                                        }
                                    }
                                }),

                            Select::make('visibility')
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
                                        } catch (Exception $e) {
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
                            DateTimePicker::make('published_at')
                                ->label('Publish At')

                                ->helperText('If you choose a future date, the article will be published at that date.')
                                ->default(now())
                        ])->columnSpan('Status'),

                        Section::make('Categories')->schema([
                            Select::make('categories')
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
                        Section::make('Sub Categories')->schema([
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

                        Section::make('Tags')->schema([
                            Select::make('tags')
                                ->label('')
                                ->relationship('tags', 'name')
                                ->createOptionForm([
                                    TextInput::make('name')
                                        ->required()
                                        ->placeholder('Tag name'),
                                   // hidden user id is logged in user
                                   Hidden::make('user_id')
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

                        Section::make('Location')->schema([
                            Select::make('locations')
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
                TextColumn::make('id')->sortable()->searchable(),
                // date created at
                TextColumn::make('created_at')->dateTime('d/m/Y H:ia')
                    ->sortable()
                    ->label('Created At'),
                TextColumn::make('user.name')->label('Created By')
                    ->limit(30)
                    ->url(fn ($record) => route('filament.admin.resources.users.view', $record->user))
                    ->openUrlInNewTab()
                    ->sortable()
                    ->searchable(),
                // change status directly togglecolumn
                ToggleColumn::make('status')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('title')
                    ->sortable()
                    ->searchable()
                    ->limit(50),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (int $state): string => Article::STATUS[$state] ?? $state)
                    ->color(fn (int $state): string => match($state) {
                        0 => 'secondary',
                        1 => 'success',
                        default => 'gray',
                    })
                    ->sortable()
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (int $state): string => Article::TYPE[$state] ?? $state)
                    ->color(fn (int $state): string => match($state) {
                        'video' => 'primary',
                        'multimedia' => 'secondary',
                        default => 'gray',
                    })
                    ->sortable(),
				TagsColumn::make('categories.name')
					->label('Parent Categories'),

				TagsColumn::make('subCategories.name')
					->label('Sub Categories'),

                TextColumn::make('hidden_from_home')
                    ->label('Hidden (Home)')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match($state) {
                        0 => 'No',
                        1 => 'Yes',
                        default => $state,
                    })
                    ->color(fn ($state) => match($state) {
                        0 => 'secondary',
                        1 => 'success',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('pinned_recommended')
                    ->label('Pinned (Recommended)')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match($state) {
                        0 => 'No',
                        1 => 'Yes',
                        default => $state,
                    })
                    ->color(fn ($state) => match($state) {
                        0 => 'secondary',
                        1 => 'success',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('available_for_web')
                    ->label('Available for Web')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match($state) {
                        0 => 'No',
                        1 => 'Yes',
                        default => $state,
                    })
                    ->color(fn ($state) => match($state) {
                        0 => 'secondary',
                        1 => 'success',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('visibility')
                    ->label('Visibility')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match($state) {
                        Article::VISIBILITY_PRIVATE => 'Private',
                        Article::VISIBILITY_PUBLIC => 'Public',
                        default => $state,
                    })
                    ->color(fn ($state) => match($state) {
                        Article::VISIBILITY_PRIVATE => 'secondary',
                        Article::VISIBILITY_PUBLIC => 'success',
                        default => 'gray',
                    })
                    ->sortable(),

                // likes count
                TextColumn::make('likes_count')
                    ->sortable()
                    ->counts('likes')
                    ->label('Likes'),
                TextColumn::make('bookmarks_count')
                    ->label('Bookmarks')
                    ->counts('bookmarks')
                    ->sortable(),
                TextColumn::make('shares_count')
                    ->label('Shares')
                    ->counts('shares')
                    ->sortable(),

                // comments count
                TextColumn::make('comments_count')
                    ->counts('comments')
                    ->sortable()
                    ->label('Comments'),

                // view count
                TextColumn::make('views_count')
                    ->counts('views')
                    ->sortable()
                    ->label('Views'),

                TextColumn::make('organic_views_count')
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

                TextColumn::make('published_at')->dateTime('d/m/Y')
                    ->sortable()
                    ->label('Publish At'),
            ])
            ->filters([
                // filter by user
                SelectFilter::make('user_id')
                    ->label('Created By')
                    ->searchable()
                    ->options(fn () => User::select('id', 'name')->get()->pluck('name', 'id')->toArray())
                    ->placeholder('All'),
                // filter by status
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(Article::STATUS)
                    ->placeholder('All'),
                // filter by hidden_from_home
                SelectFilter::make('hidden_from_home')
                    ->label('Hidden from home?')
                    ->options([
                        0 => 'No',
                        1 => 'Yes',
                    ])
                    ->placeholder('All'),

                // filter by pinned_recommended
                SelectFilter::make('pinned_recommended')
                    ->label('Pinned (Recommended)')
                    ->options([
                        0 => 'No',
                        1 => 'Yes',
                    ])
                    ->placeholder('All'),

                // filter by available_for_web
                SelectFilter::make('available_for_web')
                    ->label('Available for Web?')
                    ->options([
                        0 => 'No',
                        1 => 'Yes',
                    ])
                    ->placeholder('All'),

                // filter by has video
                SelectFilter::make('type')
                    ->label('Has Video?')
                    ->options([
                        'video' => 'Yes',
                        'multimedia' => 'No',
                    ])
                    ->placeholder('All'),
                // filter by ArticleCategory
                SelectFilter::make('categories')
                    ->label('Categories')
                    ->relationship('categories', 'name')
                    ->multiple()
                    ->searchable()
                    ->options(fn () => ArticleCategory::select('id', 'name')->get()->pluck('name', 'id')->toArray())
                    ->placeholder('All'),
                // filter by Article created_at date range
                Filter::make('created_at')
                ->schema([
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
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
                // bulk change status
                BulkAction::make('Change to Published')
                ->action(function (Collection $records) {
                    $records->each(function (Article $record) {
                        $record->update([
                            'status' => 1,
                            'published_at' => now()
                        ]);
                    });
                })->requiresConfirmation()->deselectRecordsAfterCompletion(),

                BulkAction::make('Change to Draft')
                ->action(function (Collection $records) {
                    $records->each(function (Article $record) {
                        $record->update(['status' => 0]);
                    });
                })->requiresConfirmation()->deselectRecordsAfterCompletion(),

                BulkAction::make('Move to Archived')
                ->action(function (Collection $records) {
                    $records->each(function (Article $record) {
                        $record->update(['status' => 2]);
                    });
                })->requiresConfirmation()->deselectRecordsAfterCompletion(),

                // table bulkaction to mark hidden from home toggle
                BulkAction::make('toggle_hidden_from_home')
                ->label('Toggle Home Hidden/Visible')
                ->schema([
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
                       } catch (Exception $e) {
                           Log::error('[ArticleResource] Hidden from Home Bulk Action Error', [
                               'error' => $e->getMessage()
                           ]);
                       }
                    }
                })->requiresConfirmation()->deselectRecordsAfterCompletion(),

                // toggle pinned_recommended
                BulkAction::make('toggle_pinned_recommended')
                ->label('Toggle Pinned Recommended')
                ->schema([
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

                // bulk action toggle available_for_web
                BulkAction::make('toggle_available_for_web')
                    ->label('Toggle Available for Web')
                    ->schema([
                        Toggle::make('available_for_web')
                            ->label('Available for Web')
                            ->default(true)
                            ->required(),
                        Placeholder::make('max_limit')
                            ->content('You can only have 5 articles available for web at a time.')
                    ])
                    ->action(function (Collection $records, array $data) {
                        // only check limit when enabling web availability
                        if ($data['available_for_web']) {
                            // count currently available articles (excluding selected ones)
                            $currentWebAvailable = Article::where('available_for_web', true)
                                ->published()
                                ->where('visibility', Article::VISIBILITY_PUBLIC)
                                ->whereNotIn('id', $records->pluck('id'))
                                ->count();
                            
                            // count how many selected articles would be newly enabled
                            $selectedCount = $records->count();
                            
                            // check if adding these would exceed the limit
                            if (($currentWebAvailable + $selectedCount) > 5) {
                                Notification::make()
                                    ->title('Maximum limit reached')
                                    ->body('You can only have 5 articles published, public, available for web at a time. Please unselect some articles.')
                                    ->danger()
                                    ->send();
                                    
                                return;
                            }
                        }
                        
                        // Update the records
                        $records->each(function ($record) use ($data) {
                            $record->update(['available_for_web' => $data['available_for_web']]);
                        });
                        
                        Notification::make()
                            ->title('Success')
                            ->body('Web availability updated successfully')
                            ->success()
                            ->send();
                    })
                    ->requiresConfirmation()
                    ->deselectRecordsAfterCompletion(),

				ExportBulkAction::make()
					->exports([
						ExcelExport::make()
							->label('Export Articles Categories')
							->withColumns([
								Column::make('id')->heading('article_id'),
								Column::make('user_id')
									->heading('author')
									->getStateUsing(fn($record) => $record->user ? $record->user->name : 'N/A'),
								Column::make('lang')->heading('language'),
								Column::make('status')
									->heading('status')
									->getStateUsing(fn($record) => match($record->status) {
										Article::STATUS_DRAFT => 'Draft',
										Article::STATUS_PUBLISHED => 'Published',
										Article::STATUS_ARCHIVED => 'Archived',
										default => 'Unknown'
									}),
								Column::make('hidden_from_home')
									->heading('hide from home')
									->getStateUsing(fn($record) => $record->hidden_from_home ? 'Yes' : 'No'),
								Column::make('categories.name')
									->heading('category_names')
									->getStateUsing(fn($record) => $record->categories->pluck('name')->join(',')),
								Column::make('subCategories.name')
									->heading('sub_categories')
									->getStateUsing(fn($record) => $record->subCategories->pluck('name')->join(',')),
								Column::make('tags.name')
									->heading('tags')
									->getStateUsing(fn($record) => $record->tags->pluck('name')->join(',')),
								Column::make('title')->heading('title'),
								Column::make('slug')->heading('slug'),
								Column::make('body')->heading('body')
							])
							->withFilename(fn($resource) => $resource::getModelLabel() . '-' . date('Y-m-d'))
							->withWriterType(Excel::CSV)
					]),

            ]);
    }

    public static function getRelations(): array
    {
        return [
            CommentsRelationManager::class,
            InteractionsRelationManager::class,
            LocationsRelationManager::class,
            MerchantOffersRelationManager::class,
            AuditsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListArticles::route('/'),
            'create' => CreateArticle::route('/create'),
            'edit' => EditArticle::route('/{record}/edit'),
        ];
    }
}
