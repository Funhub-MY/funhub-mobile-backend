<?php

namespace App\Filament\Resources\ArticleImports\RelationManagers;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use App\Models\Article;
use Filament\Forms;
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

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Group::make()
                    ->schema([
                        Section::make()->schema([
                            Hidden::make('user_id')
                                ->default(fn () => auth()->id()),
                            TextInput::make('title')
                                ->required()
                                ->lazy()
                                ->afterStateUpdated(fn (string $context, $state, callable $set) => $context === 'create' ? $set('slug', Str::slug($state)) : null),

                            TextInput::make('slug')
                                ->disabled()
                                ->required()
                                ->helperText('Filled automatically when you fill title')
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
                                ->maxFiles(10)
                                ->rules('image'),
                        ])->columnSpan('full')
                    ]),

                Group::make()
                    ->schema([
                        Section::make('Language')->schema([
                            Select::make('language')
                                ->options([
                                    'en' => 'English',
                                    'zh' => 'Chinese',
                                ])
                                ->default('en')
                                ->required(),
                        ])->columnSpan('Language'),

                        Section::make('Status')->schema([
                            Select::make('status')
                                ->options(Article::STATUS)->default(0),
                            DatePicker::make('published_at')
                                ->label('Published At')
                                ->default(now())
                                ->required()

                        ])->columnSpan('Status'),

                        Section::make('Categories')->schema([
                            Select::make('categories')
                                ->label('')
                                ->relationship('categories', 'name')
                                ->multiple()
                                ->preload()
                                ->placeholder('Select categories...'),
                        ]),

                        Section::make('Tags')->schema([
                            Select::make('tags')
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
                SpatieMediaLibraryImageColumn::make('image')->collection('article_cover')->label('Image'),
                TextColumn::make('title'),
                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (int $state): string => Article::TYPE[$state] ?? $state)
                    ->sortable()
                    ->searchable(),
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
                TextColumn::make('published_at')->dateTime('d-m-Y')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('user.name')->label('Created By')
                    ->sortable()->searchable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                //Tables\Actions\CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }
}
