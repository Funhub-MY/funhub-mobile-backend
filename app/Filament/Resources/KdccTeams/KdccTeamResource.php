<?php

namespace App\Filament\Resources\KdccTeams;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\KdccTeams\Pages\ListKdccTeams;
use App\Filament\Resources\KdccTeams\Pages\CreateKdccTeam;
use App\Filament\Resources\KdccTeams\Pages\EditKdccTeam;
use App\Filament\Resources\KdccTeamResource\Pages;
use App\Models\KdccTeams;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\ImageColumn;

class KdccTeamResource extends Resource
{
    protected static ?string $model = KdccTeams::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static string | \UnitEnum | null $navigationGroup = 'Events';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
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
                TextColumn::make('category_id')
                    ->label('Category')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state == 1 ? 'U17' : 'Open')
                    ->color(fn ($state) => match($state) {
                        1 => 'success',
                        2 => 'warning',
                        default => 'gray',
                    })
                    ->sortable()
                    ->searchable(),
                TextColumn::make('vote_count')
                ->sortable(),
                TextColumn::make('team_image_path')
                    ->label('Image')
                    ->url(fn ($record) => asset('storage/' . $record->team_image_path))
                    ->openUrlInNewTab()
                    ->color('primary'),
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
            //
        ];
    }
    
    public static function getPages(): array
    {
        return [
            'index' => ListKdccTeams::route('/'),
            'create' => CreateKdccTeam::route('/create'),
            'edit' => EditKdccTeam::route('/{record}/edit'),
        ];
    }    
}
