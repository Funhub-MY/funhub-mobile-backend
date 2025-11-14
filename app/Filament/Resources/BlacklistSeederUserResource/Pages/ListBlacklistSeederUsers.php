<?php

namespace App\Filament\Resources\BlacklistSeederUserResource\Pages;

use App\Filament\Resources\BlacklistSeederUserResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;
use Maatwebsite\Excel\Excel;
use pxlrbt\FilamentExcel\Actions\Pages\ExportAction;
use pxlrbt\FilamentExcel\Columns\Column;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

class ListBlacklistSeederUsers extends ListRecords
{
    protected static string $resource = BlacklistSeederUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
			ExportAction::make()
				->exports([
					ExcelExport::make()
						->withColumns([
							Column::make('id')->heading('Id'),
							Column::make('user_id')->heading('User Id'),
							Column::make('user.name')->heading('User Name'),
						])
						->withChunkSize(500)
						->withFilename(fn ($resource) => $resource::getModelLabel() . '-' . date('Y-m-d'))
						->withWriterType(Excel::CSV),
				])
        ];
    }
}
