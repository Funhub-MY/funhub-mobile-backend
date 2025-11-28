<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BadgeResource\Pages;
use App\Filament\Resources\BadgeResource\RelationManagers;
use App\Models\Badge;
use App\Models\Campaign;
use Filament\Forms;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Tapp\FilamentAuditing\RelationManagers\AuditsRelationManager;

class BadgeResource extends Resource
{
    protected static ?string $model = Badge::class;

    protected static ?string $navigationIcon = 'heroicon-o-badge-check';

    protected static ?string $navigationGroup = 'Events';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Badge Details')
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('campaign_id')
                                ->label('Campaign')
                                ->options(Campaign::pluck('title', 'id'))
                                ->required()
                                ->searchable(),

                            TextInput::make('name')
                                ->required()
                                ->unique(ignoreRecord: true)
                                ->maxLength(255)
                                ->helperText('Badge name must be unique'),
                        ]),

                        Grid::make(2)->schema([
                            ColorPicker::make('color')
                                ->label('Display Color')
                                ->nullable(),

                            Toggle::make('is_active')
                                ->label('Active')
                                ->default(true),
                        ]),

                        Textarea::make('description')
                            ->label('Description')
                            ->nullable()
                            ->rows(3)
                            ->helperText('Describe how this badge is earned')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                ColorColumn::make('color')
                    ->label('Color'),

                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('campaign.title')
                    ->label('Campaign')
                    ->searchable()
                    ->sortable()
                    ->limit(30),

                TextColumn::make('description')
                    ->label('Description')
                    ->limit(40)
                    ->toggleable(),

                TextColumn::make('user_badges_count')
                    ->label('Awarded')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                TextColumn::make('created_at')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('campaign_id')
                    ->label('Campaign')
                    ->options(Campaign::pluck('title', 'id')),

                SelectFilter::make('is_active')
                    ->options([
                        '1' => 'Active',
                        '0' => 'Inactive',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\UserBadgesRelationManager::class,
            AuditsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBadges::route('/'),
            'create' => Pages\CreateBadge::route('/create'),
            'view' => Pages\ViewBadge::route('/{record}'),
            'edit' => Pages\EditBadge::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['campaign'])
            ->withCount('userBadges');
    }
}
