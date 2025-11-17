<?php

namespace App\Filament\Resources\Users\RelationManagers;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;
use function Symfony\Component\String\s;

class EngagementHistoryRelationManager extends RelationManager
{

    protected static ?string $title = 'Engagement History';

    protected static string $relationship = 'interactions';

    protected static ?string $recordTitleAttribute = 'Interaction';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('type')
                    ->label('Type')
                    ->required()
                    ->disabled(),
                TextInput::make('created_at')
                    ->label('Created At')
                    ->required()
                    ->disabled(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('type')
                    ->label('Type')
                    ->sortable()
                    ->enum([
                        1 => 'Like',
                        2 => 'Dislike',
                        3 => 'Share',
                        4 => 'Bookmark',
                    ]),
                TextColumn::make('created_at')
                    ->label('Created At')
                    ->sortable()
                    ->dateTime('Y-m-d H:i:s'),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Type')
                    ->options([
                        1 => 'Like',
                        2 => 'Dislike',
                        3 => 'Share',
                        4 => 'Bookmark',
                    ])
                    ->default(null),
                Filter::make('created_from')
                    ->schema([
                        DatePicker::make('created_from')
                            ->placeholder('Select start date'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        if ($data['created_from']) {
                            $query->whereDate('created_at', '>=', $data['created_from']);
                        }
                    })
                    ->label('Created From'),

                Filter::make('created_until')
                    ->schema([
                        DatePicker::make('created_until')
                            ->placeholder('Select end date'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        if ($data['created_until']) {
                            $query->whereDate('created_at', '<=', $data['created_until']);
                        }
                    })
                    ->label('Created Until'),
            ])
            ->recordActions([
            ])
            ->toolbarActions([
                ExportBulkAction::make()->exports([
                    ExcelExport::make('table')
                        ->withFilename('Engagement History' . '-' . date('Y-m-d'))
                        ->fromTable(),
                ]),
                DeleteBulkAction::make(),
            ]);
    }
}
