<?php

namespace App\Filament\Resources;

use Filament\Forms;
use Filament\Tables;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
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

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Campaigns';

    protected static ?int $navigationSort = 4;



    public static function form(Form $form): Form
    {
        return $form
            ->schema([
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
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),

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
            'index' => Pages\ListCampaignRespondantDetails::route('/'),
            'create' => Pages\CreateCampaignRespondantDetail::route('/create'),
            'edit' => Pages\EditCampaignRespondantDetail::route('/{record}/edit'),
        ];
    }
}
