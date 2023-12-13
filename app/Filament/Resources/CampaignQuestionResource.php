<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CampaignQuestionResource\Pages;
use App\Filament\Resources\CampaignQuestionResource\RelationManagers;
use App\Models\CampaignQuestion;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CampaignQuestionResource extends Resource
{
    protected static ?string $model = CampaignQuestion::class;

    protected static ?string $navigationIcon = 'heroicon-o-collection';

    protected static ?string $navigationGroup = 'Campaigns';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Campaign Question Details')
                    ->columns(2)
                    ->schema([
                        Select::make('campaign_id')
                            ->relationship('campaign', 'title')
                            ->searchable()
                            ->required(),
                        TextInput::make('brand')
                            ->required(),
                        Textarea::make('question')
                            ->required(),
                        Textarea::make('answer')
                            ->required(),
                        Toggle::make('is_active')
                            ->default(true)
                            ->required(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('campaign.title')
                    ->label('Campaign')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('brand')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('question')
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
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCampaignQuestions::route('/'),
            'create' => Pages\CreateCampaignQuestion::route('/create'),
            'edit' => Pages\EditCampaignQuestion::route('/{record}/edit'),
        ];
    }
}
