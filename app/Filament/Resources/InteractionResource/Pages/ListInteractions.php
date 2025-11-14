<?php

namespace App\Filament\Resources\InteractionResource\Pages;

use App\Filament\Resources\InteractionResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;
use Maatwebsite\Excel\Excel;
use pxlrbt\FilamentExcel\Actions\Pages\ExportAction;
use pxlrbt\FilamentExcel\Columns\Column;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

class ListInteractions extends ListRecords
{
    protected static string $resource = InteractionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
			ExportAction::make()
				->exports([
					ExcelExport::make()
						->withColumns([
							Column::make('id')->heading('Interaction Id'),
							Column::make('user_id')->heading('User Id'),
							Column::make('user.name')->heading('By User'),
							Column::make('interactable_type')
								->heading('Interactable Type')
								->formatStateUsing(function ($state) {
									return class_basename($state);
								}),
							Column::make('interactable_id')
								->heading('Interactable Id'),
							Column::make('type')
								->heading('Type')
								->formatStateUsing(fn ($record) => [
									1 => 'Like',
									2 => 'Dislike',
									3 => 'Share',
									4 => 'Bookmark',
								][$record->type] ?? ''),
							Column::make('status')
								->heading('Status')
								->formatStateUsing(fn ($record) => [
									0 => 'Draft',
									1 => 'Published',
									2 => 'Hidden',
								][$record->status] ?? ''),
							Column::make('created_at')->heading('Created At'),
						])
						->withChunkSize(500)
						->withFilename(fn ($resource) => $resource::getModelLabel() . '-' . date('Y-m-d'))
						->withWriterType(Excel::CSV),
				]),
        ];
    }
}
