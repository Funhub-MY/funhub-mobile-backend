<?php

namespace App\Filament\Resources;

use App\Filament\Resources\KdccTeamResource\Pages;
use App\Models\KdccTeams;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\ImageColumn;

class KdccTeamResource extends Resource
{
    protected static ?string $model = KdccTeams::class;

    protected static ?string $navigationIcon = 'heroicon-o-collection';

    protected static ?string $navigationGroup = 'Events';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Team Information')
                    ->schema([
                        TextInput::make('name')
                            ->label('Team Name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Enter team name'),

                        Select::make('category_id')
                            ->label('Category')
                            ->required()
                            ->options([
                                1 => 'U17',
                                2 => 'Open',
                            ])
                            ->default('Open'),

                            
                        FileUpload::make('team_image_path')
                            ->label('Team Image')
                            ->image()
                            ->disk('public')
                            ->directory('images/kdcc')
                            ->nullable()
                            ->maxSize(5120)
                            ->hint('Accepted formats: JPG, PNG, GIF. Max size: 5MB')
                            ->imagePreviewHeight(200)
                            ->preserveFilenames() // Keeps original filename
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                BadgeColumn::make('category_id')
                ->label('Category')
                ->formatStateUsing(fn ($state) => $state == 1 ? 'U17' : 'Open')
                ->colors([
                    'success' => 1,
                    'warning' => 2,
                ])
                ->sortable()
                ->searchable(),
                TextColumn::make('vote_count')
                ->sortable(),
                TextColumn::make('team_image_path')
                ->searchable(),
                ImageColumn::make('team_image_path')
                ->label('Image')
                ->disk('public')
            ])
            ->actions([
                \Filament\Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                \Filament\Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListKdccTeams::route('/'),
            'create' => Pages\CreateKdccTeam::route('/create'),
            'edit' => Pages\EditKdccTeam::route('/{record}/edit'),
        ];
    }    
}
