<?php

namespace App\Filament\Resources;

use Filament\Forms;
use Filament\Tables;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use App\Models\SupportRequestCategory;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\SupportRequestCategoryResource\Pages;
use Tapp\FilamentAuditing\RelationManagers\AuditsRelationManager;
use App\Filament\Resources\SupportRequestCategoryResource\RelationManagers;
use Filament\Forms\Components\KeyValue;

class SupportRequestCategoryResource extends Resource
{
    protected static ?string $model = SupportRequestCategory::class;

    protected static ?string $navigationGroup = 'Help Center';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->placeholder('Enter name')
                        ->autofocus()
                        ->label('Name'),

                    KeyValue::make('name_translation')
                        ->label('Name Translation')
                        ->keyLabel('Language')
                        ->valueLabel('Name Translation')
                        ->disableAddingRows()
                        ->disableDeletingRows()
                        ->disableEditingKeys()
                        ->afterStateHydrated(function ($context, $state, callable $set, $record) {
                            $locales = config('app.available_locales', []);
                            if ($context === 'edit' && $record) {
                                $translations = json_decode($record->name_translation, true);
                                foreach ($locales as $language) {
                                    $languageCode = array_search($language, $locales);
                                    $set("name_translation.$language", $translations[$languageCode] ?? '');
                                }
                            } else {
                                foreach ($locales as $language) {
                                    $set("name_translation.$language", '');
                                }
                            }
                        })
                        ->dehydrateStateUsing(function ($state) {
                            $locales = config('app.available_locales', []);
                            $transformedState = [];
                            foreach ($state as $key => $value) {
                                $localeKey = array_search($key, $locales);
                                if ($localeKey !== false) {
                                    $transformedState[$localeKey] = $value;
                                }
                            }
                            return json_encode($transformedState);
                        }),

                    Select::make('type')
                        ->required()
                        ->options([
                            'complain' => 'Complain',
                            'bug' => 'Bug',
                            'feature_request' => 'Feature Request',
                            'information_update' => 'Information Update',
                            'others' => 'Others',
                        ]),

                    // Forms\Components\TextInput::make('description'),

                    KeyValue::make('description_translation')
                        ->label('Description Translation')
                        ->keyLabel('Language')
                        ->valueLabel('Description Translation')
                        ->disableAddingRows()
                        ->disableDeletingRows()
                        ->disableEditingKeys()
                        ->afterStateHydrated(function ($context, $state, callable $set, $record) {
                            $locales = config('app.available_locales', []);
                            if ($context === 'edit' && $record) {
                                $translations = json_decode($record->description_translation, true);
                                foreach ($locales as $language) {
                                    $languageCode = array_search($language, $locales);
                                    $set("description_translation.$language", $translations[$languageCode] ?? '');
                                }
                            } else {
                                foreach ($locales as $language) {
                                    $set("description_translation.$language", '');
                                }
                            }
                        })
                        ->dehydrateStateUsing(function ($state) {
                            $locales = config('app.available_locales', []);
                            $transformedState = [];
                            foreach ($state as $key => $value) {
                                $localeKey = array_search($key, $locales);
                                if ($localeKey !== false) {
                                    $transformedState[$localeKey] = $value;
                                }
                            }
                            return json_encode($transformedState);
                        }),

                    Select::make('status')
                        ->required()
                        ->options([
                            0 => 'Draft',
                            1 => 'Published',
                            2 => 'Archived',
                        ]),

                    Hidden::make('user_id')
                        ->default(fn () => auth()->id())
                ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->sortable()
                    ->searchable(),

                BadgeColumn::make('type')
                    ->label('Type')
                    ->enum([
                        'complain' => 'Complain',
                        'bug' => 'Bug',
                        'feature_request' => 'Feature Request',
                        'others' => 'Others',
                    ])
                    ->colors([
                        'secondary' => 'complain',
                        'success' => 'bug',
                        'warning' => 'feature_request',
                        'danger' => 'others',
                    ]),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->enum([
                        0 => 'Draft',
                        1 => 'Published',
                        2 => 'Archived',
                    ])
                    ->colors([
                        'secondary' => 0,
                        'success' => 1,
                        'danger' => 2,
                    ])
                    ->sortable()
                    ->searchable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        'complain' => 'Complain',
                        'bug' => 'Bug',
                        'feature_request' => 'Feature Request',
                        'others' => 'Others',
                    ])
                    ->placeholder('Filter by type'),
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
            'index' => Pages\ListSupportRequestCategories::route('/'),
            'create' => Pages\CreateSupportRequestCategory::route('/create'),
            'edit' => Pages\EditSupportRequestCategory::route('/{record}/edit'),
        ];
    }
}
