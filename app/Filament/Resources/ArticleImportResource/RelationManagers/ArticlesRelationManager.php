<?php

namespace App\Filament\Resources\ArticleImportResource\RelationManagers;

use App\Models\Article;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;

class ArticlesRelationManager extends RelationManager
{
    protected static string $relationship = 'articles';

    protected static ?string $recordTitleAttribute = 'title';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make()->schema([
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
                    ]),

                Forms\Components\Group::make()
                    ->schema([
                        Section::make('Language')->schema([
                            Forms\Components\Select::make('language')
                                ->options([
                                    'en' => 'English',
                                    'zh' => 'Chinese',
                                ])
                                ->default('en')
                                ->required(),
                        ])->columnSpan('Language'),

                        Forms\Components\Section::make('Status')->schema([
                            Forms\Components\Select::make('status')
                                ->options(Article::STATUS)->default(0),
                            Forms\Components\DatePicker::make('published_at')
                                ->label('Published At')
                                ->default(now())
                                ->required()

                        ])->columnSpan('Status'),

                        Forms\Components\Section::make('Categories')->schema([
                            Forms\Components\Select::make('categories')
                                ->label('')
                                ->relationship('categories', 'name')
                                ->multiple()
                                ->preload()
                                ->placeholder('Select categories...'),
                        ]),

                        Forms\Components\Section::make('Tags')->schema([
                            Forms\Components\Select::make('tags')
                                ->label('')
                                ->relationship('tags', 'name')
                                ->multiple()
                                ->preload()
                                ->placeholder('Select tags...'),
                        ]),

                    ])
            ])
            ->columns(1);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\SpatieMediaLibraryImageColumn::make('image')->collection('article_cover')->label('Image'),
                Tables\Columns\TextColumn::make('title'),
                Tables\Columns\TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (int $state): string => Article::TYPE[$state] ?? $state)
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
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
                Tables\Columns\TextColumn::make('published_at')->dateTime('d-m-Y')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('user.name')->label('Created By')
                    ->sortable()->searchable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                //Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }
}
