<?php

namespace App\Filament\Resources;

use Filament\Forms;
use Filament\Tables;
use App\Models\Campaign;
use Filament\Resources\Form;
use Filament\Resources\Table;
use Filament\Resources\Resource;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\CampaignResource\Pages;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\CampaignResource\RelationManagers;
use Tapp\FilamentAuditing\RelationManagers\AuditsRelationManager;

class CampaignResource extends Resource
{
    protected static ?string $model = Campaign::class;

    protected static ?string $navigationIcon = 'heroicon-o-collection';

    protected static ?string $navigationGroup = 'Campaigns';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Campaign Details')
                    ->schema([
                        TextInput::make('order')
                            ->required()
                            ->numeric()
                            ->label('Order')
                            ->default(fn () => (Campaign::max('order') ?? 0) + 1),
                        TextInput::make('title')
                            ->autofocus()
                            ->required(),
                        Textarea::make('description')
                            ->autofocus()
                            ->required(),
                        TextInput::make('url')
                            ->label('Campaign Mobile Site (URL)')
                            ->helperText('Will be displayed as web view in app.')
                            ->required(),

                        Forms\Components\SpatieMediaLibraryFileUpload::make('event_banner')
                            ->label('Event Banner Image')
                            ->collection(Campaign::EVENT_COLLECTION)
                            ->columnSpan('full')
                            ->disk(function () {
                                if (config('filesystems.default') === 's3') {
                                    return 's3_public';
                                }
                            })
                            ->acceptedFileTypes(['image/*'])
                            ->maxFiles(20)
                            ->enableReordering()
                            ->appendFiles()
                            ->rules('image')
                            ->required(),

                        Forms\Components\SpatieMediaLibraryFileUpload::make('banner')
                            ->label('Banner Image (Home)')
                            ->collection(Campaign::BANNER_COLLECTION)
                            ->columnSpan('full')
                            ->disk(function () {
                                if (config('filesystems.default') === 's3') {
                                    return 's3_public';
                                }
                            })
                            ->acceptedFileTypes(['image/*'])
                            ->maxFiles(20)
                            ->enableReordering()
                            ->appendFiles()
                            ->rules('image'),

                        Forms\Components\SpatieMediaLibraryFileUpload::make('icon')
                            ->label('Floating Button Image')
                            ->collection(Campaign::ICON_COLLECTION)
                            ->columnSpan('full')
                            ->disk(function () {
                                if (config('filesystems.default') === 's3') {
                                    return 's3_public';
                                }
                            })
                            ->acceptedFileTypes(['image/*'])
                            ->maxFiles(20)
                            ->enableReordering()
                            ->appendFiles()
                            ->rules('image'),


                        Toggle::make('is_active')
                            ->autofocus()
                            ->required(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order')
                    ->sortable()
                    ->label('Order'),
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\ToggleColumn::make('is_active')
                    ->sortable()
                    ->searchable(),
            ])
            ->filters([
                //
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
            'index' => Pages\ListCampaigns::route('/'),
            'create' => Pages\CreateCampaign::route('/create'),
            'edit' => Pages\EditCampaign::route('/{record}/edit'),
        ];
    }
}
