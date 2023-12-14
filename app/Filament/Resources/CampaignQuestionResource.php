<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CampaignQuestionResource\Pages;
use App\Filament\Resources\CampaignQuestionResource\RelationManagers;
use App\Models\CampaignQuestion;
use Closure;
use Filament\Forms;
use Filament\Forms\Components\Repeater;
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
                            ->hidden(fn (Closure $get) => $get('answer_type') == 'text')
                            ->rules('min:1'),
                    ]),
                Section::make('Media')
                    ->columns(2)
                    ->schema([
                        Forms\Components\SpatieMediaLibraryFileUpload::make('question_banner')
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

                        Forms\Components\SpatieMediaLibraryFileUpload::make('footer_banner')
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
