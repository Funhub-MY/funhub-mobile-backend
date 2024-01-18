<?php

namespace App\Filament\Resources;

use Filament\Forms;
use Filament\Tables;
use Filament\Resources\Form;
use Filament\Resources\Table;
use Filament\Resources\Resource;
use Filament\Forms\Components\Select;
use App\Models\CampaignQuestionAnswer;
use Filament\Forms\Components\Textarea;
use Illuminate\Database\Eloquent\Model;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\CampaignQuestionAnswerResource\Pages;
use Tapp\FilamentAuditing\RelationManagers\AuditsRelationManager;
use App\Filament\Resources\CampaignQuestionAnswerResource\RelationManagers;
use Filament\Tables\Columns\TextColumn;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use pxlrbt\FilamentExcel\Columns\Column;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

class CampaignQuestionAnswerResource extends Resource
{
    protected static ?string $model = CampaignQuestionAnswer::class;

    protected static ?string $navigationIcon = 'heroicon-o-collection';

    protected static ?string $navigationGroup = 'Campaigns';

    protected static ?int $navigationSort = 3;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('campaign_question_id')
                    ->label('Campaign Question')
                    ->relationship('question', 'question')
                    ->required(),

                Select::make('user')
                    ->label('User')
                    ->relationship('user', 'name')
                    ->required(),

                Textarea::make('answer')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([

                Tables\Columns\TextColumn::make('question.question')
                    ->label('Campaign Question')
                    ->searchable(),

                // brand
                TextColumn::make('question.brand')
                    ->label('Brand')
                    ->searchable(),

                Tables\Columns\TextColumn::make('user.name')
                    ->searchable()
                    ->label('User'),

                Tables\Columns\TextColumn::make('answer')
                    ->searchable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->sortable()
            ])
            ->filters([
                SelectFilter::make('campaign_question_id')
                    ->label('Campaign Question')
                    ->relationship('question', 'question'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),

                ExportBulkAction::make()->exports([
                    ExcelExport::make()->fromTable()->withColumns([
                        Column::make('user.id')->heading('User ID'),
                        Column::make('user.name')->heading('User Name'),
                        Column::make('user.email')->heading('User Email'),
                        Column::make('question.brand')->heading('Brand'),
                        Column::make('question.question')->heading('Question'),
                        Column::make('answer'),
                        Column::make('created_at')->heading('Created At'),
                    ]),
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
            'index' => Pages\ListCampaignQuestionAnswers::route('/'),
            'create' => Pages\CreateCampaignQuestionAnswer::route('/create'),
            'edit' => Pages\EditCampaignQuestionAnswer::route('/{record}/edit'),
        ];
    }
}
