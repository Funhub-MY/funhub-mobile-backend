<?php

namespace App\Filament\Resources\Campaigns;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\Campaigns\Pages\ListCampaigns;
use App\Filament\Resources\Campaigns\Pages\CreateCampaign;
use App\Filament\Resources\Campaigns\Pages\EditCampaign;
use Filament\Forms;
use Filament\Tables;
use App\Models\Campaign;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Filament\Forms\Components\Toggle;
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

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static string | \UnitEnum | null $navigationGroup = 'Campaigns';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Campaign Details')
                    ->schema([
                        TextInput::make('order')
                            ->required()
                            ->numeric()
                            ->unique(ignoreRecord: true)
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

                        SpatieMediaLibraryFileUpload::make('event_banner')
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

                        SpatieMediaLibraryFileUpload::make('banner')
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

                        SpatieMediaLibraryFileUpload::make('icon')
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
                            ->rules('image')
                            ->required(),


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
                TextColumn::make('order')
                    ->sortable()
                    ->label('Order'),
                TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                ToggleColumn::make('is_active')
                    ->sortable()
                    ->searchable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
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
            'index' => ListCampaigns::route('/'),
            'create' => CreateCampaign::route('/create'),
            'edit' => EditCampaign::route('/{record}/edit'),
        ];
    }
}
