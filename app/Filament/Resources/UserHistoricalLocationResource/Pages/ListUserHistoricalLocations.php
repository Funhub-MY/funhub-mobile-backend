<?php

namespace App\Filament\Resources\UserHistoricalLocationResource\Pages;

use App\Filament\Resources\UserHistoricalLocationResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;
use pxlrbt\FilamentExcel\Actions\Pages\ExportAction;
use pxlrbt\FilamentExcel\Columns\Column;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

class ListUserHistoricalLocations extends ListRecords
{
    protected static string $resource = UserHistoricalLocationResource::class;

    protected function getActions(): array
    {
        return [
            Actions\CreateAction::make(),

            ExportAction::make()
                ->exports([
                    ExcelExport::make()
                        ->label('Export User Historical Locations (CSV)')
                        ->withColumns([
                            Column::make('created_at')
                                ->heading('Recorded On')
                                ->formatStateUsing(fn($record) => $record->created_at->format('d/m/Y h:iA')),
                            Column::make('user.name')->heading('User'),
                            Column::make('lat')->heading('Latitude'),
                            Column::make('lng')->heading('Longitude'),
                            Column::make('address')->heading('Address'),
                            Column::make('address_2')->heading('Address 2'),
                            Column::make('zip_code')->heading('Zip Code'),
                            Column::make('city')->heading('City'),
                            Column::make('state')->heading('State'),
                        ])
                        ->withFilename(fn ($resource) => $resource::getModelLabel() . '-' . date('Y-m-d'))
                        ->withWriterType(\Maatwebsite\Excel\Excel::CSV)
                ]),
        ];
    }
}
