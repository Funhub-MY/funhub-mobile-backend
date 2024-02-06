<?php

namespace App\Filament\Widgets;

use App\Models\ExportableReport;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Tables\Columns\TextColumn;
use Filament\Widgets\TableWidget;

use pxlrbt\FilamentExcel\Actions\Tables\ExportAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;
use pxlrbt\FilamentExcel\Columns\Column;

class SalesReportsExporter extends TableWidget
{
    use HasWidgetShield;
    protected int | string | array $columnSpan = 'full';

    protected function getTableHeading(): string | null
    {
        return null;
    }

    protected function getTableQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return ExportableReport::query();
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('name')
                ->sortable(),

            TextColumn::make('description'),

            ExportAction::make()->exports([
                ExcelExport::make()->fromModel()->withColumns([
                    Column::make('name'),
                    Column::make('created_at'),
                    Column::make('deleted_at'),
                ])->modifyQueryUsing(fn ($query) => $query->where('exportable', true))
            ])
        ];
    }

    protected function getTableFilters(): array
    {
        return [];
    }
}
