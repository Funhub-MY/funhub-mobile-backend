<?php

namespace App\Filament\Resources;

use App\Models\Faq;
use Filament\Forms;
use Filament\Tables;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Section;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\FaqResource\Pages;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\FaqResource\RelationManagers;
use Tapp\FilamentAuditing\RelationManagers\AuditsRelationManager;

class FaqResource extends Resource
{
    protected static ?string $model = Faq::class;

    protected static ?string $navigationGroup = 'Help Center';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Hidden::make('user_id')
                    ->default(fn () => auth()->user()->id),
                Group::make([
                    Section::make('FAQ')
                        ->schema([
                            TextInput::make('question')
                                ->autofocus()
                                ->required(),
                            Forms\Components\RichEditor::make('answer')
                                        ->required()
                                        ->placeholder('Write something...'),
                        ])
                ])->columnSpan(['lg' => 2]),

                Group::make([
                    Section::make('Info')
                        ->schema([
                            Select::make('faq_category_id')
                                ->relationship('category', 'name')
                                ->placeholder('Select Category')
                                ->preload()
                                ->searchable()
                                ->required(),

                            Select::make('language')
                                ->options([
                                    'cn' => 'Chinese',
                                    'en' => 'English',
                                ])
                                ->default('cn'),

                            Select::make('status')
                                ->options([
                                    '0' => 'Draft',
                                    '1' => 'Published',
                                ]),

                            Toggle::make('is_featured')
                                ->label('Is Featured ?')
                                ->default(false),
                        ])
                ])->columnSpan(['lg' => 2])
            ])->columns(4);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('question')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('category.name')
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match($state) {
                        0 => 'Draft',
                        1 => 'Published',
                        default => $state,
                    })
                    ->color(fn ($state) => match($state) {
                        0 => 'secondary',
                        1 => 'success',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('language')
                    ->formatStateUsing(function ($state) {
                        return strtoupper($state);
                    })
                    ->sortable(),

                TextColumn::make('is_featured')
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

                TextColumn::make('created_at')
                    ->label('Created')
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        0 => 'Draft',
                        1 => 'Published',
                    ]),

                SelectFilter::make('category')
                    ->relationship('category', 'name'),
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
            'index' => Pages\ListFaqs::route('/'),
            'create' => Pages\CreateFaq::route('/create'),
            'edit' => Pages\EditFaq::route('/{record}/edit'),
        ];
    }
}
