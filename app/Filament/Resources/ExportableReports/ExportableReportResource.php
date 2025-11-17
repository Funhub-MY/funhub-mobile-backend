<?php

namespace App\Filament\Resources\ExportableReports;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\ExportableReports\Pages\ListExportableReports;
use App\Filament\Resources\ExportableReports\Pages\CreateExportableReport;
use App\Filament\Resources\ExportableReports\Pages\EditExportableReport;
use App\Filament\Resources\ExportableReportResource\Pages;
use App\Filament\Resources\ExportableReportResource\RelationManagers;
use App\Models\ExportableReport;
use Filament\Forms;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ExportableReportResource extends Resource
{
    protected static ?string $model = ExportableReport::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static string | \UnitEnum | null $navigationGroup = 'Settings';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make([
                    TextInput::make('name')
                        ->required(),

                    TextInput::make('description'),

                    Textarea::make('columns')
                        ->helperText('JSON format'),

                    Toggle::make('is_active')
                        ->default(true),
                ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                ToggleColumn::make('is_active')
                    ->label('Active')
                    ->sortable(),
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
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListExportableReports::route('/'),
            'create' => CreateExportableReport::route('/create'),
            'edit' => EditExportableReport::route('/{record}/edit'),
        ];
    }
}
