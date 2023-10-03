<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SupportRequestCategoryResource\Pages;
use App\Filament\Resources\SupportRequestCategoryResource\RelationManagers;
use App\Models\SupportRequestCategory;
use Filament\Forms;
use Filament\Forms\Components\Card;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SupportRequestCategoryResource extends Resource
{
    protected static ?string $model = SupportRequestCategory::class;

    protected static ?string $navigationGroup = 'Help Center';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationIcon = 'heroicon-o-collection';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Card::make([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->placeholder('Enter name')
                        ->autofocus()
                        ->label('Name'),

                    Select::make('type')
                        ->required()
                        ->options([
                            'complain' => 'Complain',
                            'bug' => 'Bug',
                            'feature_request' => 'Feature Request',
                            'others' => 'Others',
                        ]),

                    Forms\Components\TextInput::make('description'),

                    Select::make('status')
                        ->required()
                        ->options([
                            0 => 'Draft',
                            1 => 'Published',
                            2 => 'Archived',
                        ]),

                    Hidden::make('user_id')
                        ->default(fn () => auth()->id())
                ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->sortable()
                    ->searchable(),

                BadgeColumn::make('type')
                    ->label('Type')
                    ->enum([
                        'complain' => 'Complain',
                        'bug' => 'Bug',
                        'feature_request' => 'Feature Request',
                        'others' => 'Others',
                    ])
                    ->colors([
                        'secondary' => 'complain',
                        'success' => 'bug',
                        'warning' => 'feature_request',
                        'danger' => 'others',
                    ]),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->enum([
                        0 => 'Draft',
                        1 => 'Published',
                        2 => 'Archived',
                    ])
                    ->colors([
                        'secondary' => 0,
                        'success' => 1,
                        'danger' => 2,
                    ])
                    ->sortable()
                    ->searchable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        'complain' => 'Complain',
                        'bug' => 'Bug',
                        'feature_request' => 'Feature Request',
                        'others' => 'Others',
                    ])
                    ->placeholder('Filter by type'),
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
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSupportRequestCategories::route('/'),
            'create' => Pages\CreateSupportRequestCategory::route('/create'),
            'edit' => Pages\EditSupportRequestCategory::route('/{record}/edit'),
        ];
    }
}
