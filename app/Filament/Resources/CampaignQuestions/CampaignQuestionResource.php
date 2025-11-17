<?php

namespace App\Filament\Resources\CampaignQuestions;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\CampaignQuestions\Pages\ListCampaignQuestions;
use App\Filament\Resources\CampaignQuestions\Pages\CreateCampaignQuestion;
use App\Filament\Resources\CampaignQuestions\Pages\EditCampaignQuestion;
use Closure;
use Filament\Forms;
use Filament\Tables;
use Filament\Tables\Table;
use App\Models\CampaignQuestion;
use Filament\Resources\Resource;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\CampaignQuestionResource\Pages;
use Tapp\FilamentAuditing\RelationManagers\AuditsRelationManager;
use App\Filament\Resources\CampaignQuestionResource\RelationManagers;

class CampaignQuestionResource extends Resource
{
    protected static ?string $model = CampaignQuestion::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static string | \UnitEnum | null $navigationGroup = 'Campaigns';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Campaign Question Details')
                    ->columns(1)
                    ->schema([
                        Select::make('campaign_id')
                            ->relationship('campaign', 'title')
                            ->searchable()
                            ->preload()
                            ->required(),
                        TextInput::make('brand')
                            ->required(),
                        Textarea::make('question')
                            ->required(),
                        Toggle::make('is_active')
                            ->default(true)
                            ->required(),
                    ]),
                Section::make('Answers')
                    ->columns(2)
                    ->schema([
                        Select::make('answer_type')
                            ->options([
                                'text' => 'Text',
                                'multichoice' => 'Multichoice Checkbox',
                                'singlechoice' => 'Single Choice',
                                'select' => 'Select',
                            ])
                            ->reactive()
                            ->default('text')
                            ->required(),

                        Repeater::make('answers')
                            ->schema([
                                TextInput::make('answer')
                                    ->required(),
                            ])
                            ->orderable()
                            ->rules('array')
                            ->hidden(fn (Get $get) => $get('answer_type') == 'text')
                            ->rules('min:1'),

                        Textarea::make('default_answer')
                            ->helperText('To be displayed at end of questionaire submisison.'),
                    ]),
                Section::make('Media')
                    ->columns(2)
                    ->schema([
                        SpatieMediaLibraryFileUpload::make('question_banner')
                            ->label('Question Banner')
                            ->collection(CampaignQuestion::QUESTION_BANNER)
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

                        SpatieMediaLibraryFileUpload::make('footer_banner')
                            ->label('Footer Banner')
                            ->collection(CampaignQuestion::FOOTER_BANNER)
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
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('campaign.title')
                    ->label('Campaign')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('brand')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('question')
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
            'index' => ListCampaignQuestions::route('/'),
            'create' => CreateCampaignQuestion::route('/create'),
            'edit' => EditCampaignQuestion::route('/{record}/edit'),
        ];
    }
}
