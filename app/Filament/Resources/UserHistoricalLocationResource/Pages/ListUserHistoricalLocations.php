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
							Column::make('user_id')->heading('User ID')->getStateUsing(fn($record) => $record->user->id),
							Column::make('user_name')->heading('User Name')->getStateUsing(fn($record) => $record->user->name),
							Column::make('age')->heading('Age')
								->getStateUsing(fn($record) => $record->user->dob ? now()->diffInYears($record->user->dob) : null),
							Column::make('gender')->heading('Gender')
								->getStateUsing(fn($record) => $record->user->gender ?? null),
							Column::make('lat')->heading('Latitude'),
							Column::make('lng')->heading('Longitude'),
							Column::make('address')->heading('Address'),
							Column::make('address_2')->heading('Address 2'),
							Column::make('zip_code')->heading('ZipCode'),
							Column::make('city')->heading('City'),
							Column::make('state')->heading('State'),
							Column::make('country')->heading('Country'),
                        ])
						->withChunkSize(500)
                        ->withFilename(fn ($resource) => $resource::getModelLabel() . '-' . date('Y-m-d'))
                        ->withWriterType(\Maatwebsite\Excel\Excel::CSV)
                ]),
        ];
    }
}
