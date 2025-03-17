<?php

namespace App\Filament\Resources\UserContactResource\Pages;

use App\Filament\Resources\UserContactResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;
use Maatwebsite\Excel\Excel;
use pxlrbt\FilamentExcel\Actions\Pages\ExportAction;
use pxlrbt\FilamentExcel\Columns\Column;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

class ListUserContacts extends ListRecords
{
    protected static string $resource = UserContactResource::class;

    protected function getActions(): array
    {
        return [
            Actions\CreateAction::make(),
			ExportAction::make()
				->exports([
					ExcelExport::make()
						->withColumns([
							Column::make('id')->heading('Contact Id'),
							Column::make('imported_by_id')->heading('By User Id'),
							Column::make('importedByUser.name')->heading('By User Name'),
							Column::make('name')->heading('Name'),
							Column::make('phone_country_code')->heading('Phone Country Code'),
							Column::make('phone_no')->heading('Phone No'),
							Column::make('related_user_id')->heading('Related User Id'),
							Column::make('relatedUser.name')->heading('Related User Name'),
							Column::make('created_at')
								->heading('Created At')
								->formatStateUsing(fn ($state) => $state?->format('Y-m-d H:i:s')),
						])
						->modifyQueryUsing(fn ($query) => $query->limit(10))
						->withChunkSize(500)
						->withFilename(fn ($resource) => $resource::getModelLabel() . '-' . date('Y-m-d'))
						->withWriterType(Excel::CSV),
				])
        ];
    }
}
