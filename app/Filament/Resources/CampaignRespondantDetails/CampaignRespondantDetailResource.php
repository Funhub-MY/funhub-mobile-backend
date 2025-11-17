<?php

namespace App\Filament\Resources\CampaignRespondantDetails;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\CampaignRespondantDetails\Pages\ListCampaignRespondantDetails;
use App\Filament\Resources\CampaignRespondantDetails\Pages\CreateCampaignRespondantDetail;
use App\Filament\Resources\CampaignRespondantDetails\Pages\EditCampaignRespondantDetail;
use Filament\Forms;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use App\Models\CampaignRespondantDetail;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Tapp\FilamentAuditing\RelationManagers\AuditsRelationManager;
use App\Filament\Resources\CampaignRespondantDetailResource\Pages;
use App\Filament\Resources\CampaignRespondantDetailResource\RelationManagers;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;
class CampaignRespondantDetailResource extends Resource
{
    protected static ?string $model = CampaignRespondantDetail::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static string | \UnitEnum | null $navigationGroup = 'Campaigns';

    protected static ?int $navigationSort = 4;



    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Campaign Respondant Details')
                    ->schema([
                        Select::make('campaign_id')
                            ->relationship('campaign', 'title')
                            ->preload()
                            ->required(),

                        Select::make('user_id')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->required(),

                        TextInput::make('name')
                            ->required(),

                        TextInput::make('ic')
                            ->label('IC')
                            ->required(),

                        TextInput::make('email')
                            ->required(),

                        TextInput::make('phone')
                            ->required(),

                        TextInput::make('address')
                            ->required(),
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('campaign.title')
                    ->label('Campaign')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('user.name')
                    ->label('User')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('name')
                    ->label('Name')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('ic')
                    ->label('IC')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('phone')
                    ->label('Phone')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('address')
                    ->label('Address')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Created At')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),

                ExportBulkAction::make()->exports([
                    ExcelExport::make('table')->fromTable(),
                ]),
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
            'index' => ListCampaignRespondantDetails::route('/'),
            'create' => CreateCampaignRespondantDetail::route('/create'),
            'edit' => EditCampaignRespondantDetail::route('/{record}/edit'),
        ];
    }
}
