<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SupportRequestResource\Pages;
use App\Filament\Resources\SupportRequestResource\RelationManagers;
use App\Filament\Resources\SupportRequestResource\RelationManagers\MessagesRelationManager;
use App\Models\SupportRequest;
use Filament\Forms;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SupportRequestResource extends Resource
{
    protected static ?string $model = SupportRequest::class;

    protected static ?string $navigationGroup = 'Help Center';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationIcon = 'heroicon-o-collection';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Group::make([
                    Section::make('Support Request')
                        ->schema([
                            TextInput::make('title')
                                ->required(),

                            Select::make('category_id')
                                ->relationship('category', 'name')
                                ->required(),

                            TextInput::make('internal_remarks'),
                        ])
                ])->columnSpan(['lg' => 2]),
                Group::make([
                    Section::make('Other Info')
                        ->schema([
                            Select::make('status')
                                ->options(SupportRequest::STATUS)
                                ->required(),

                            Select::make('requestor_id')
                                ->relationship('requestor', 'name')
                                ->searchable(),

                            Select::make('assignee_id')
                                ->relationship('assignee', 'name')
                                ->searchable(),
                        ])
                ])->columnSpan(['lg' => 2])
            ])->columns(4);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Created At')
                    ->date('d/m/Y h:iA')
                    ->sortable(),

                TextColumn::make('title')
                    ->searchable(),

                BadgeColumn::make('status')
                    ->enum([
                        0 => 'Pending',
                        1 => 'In Progress',
                        2 => 'Pending Info',
                        3 => 'Closed',
                        4 => 'Reopened',
                        5 => 'Invalid'
                    ])
                    ->colors([
                        'secondary' => 0,
                        'info' => 1,
                        'warning' => 2,
                        'success' => 3,
                        'danger' => 4,
                        'secondary' => 5,
                    ]),

                TextColumn::make('category.name')
                    ->label('Category')
                    ->searchable(),

                TextColumn::make('requestor.name')
                    ->label('Requestor')
                    ->searchable(),

                TextColumn::make('assignee.name')
                    ->label('Assignee')
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
            MessagesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSupportRequests::route('/'),
            'create' => Pages\CreateSupportRequest::route('/create'),
            'edit' => Pages\EditSupportRequest::route('/{record}/edit'),
        ];
    }
}
