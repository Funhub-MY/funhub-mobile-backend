<?php

namespace App\Filament\Resources\ArticleResource\Pages;

use App\Filament\Actions\SyncArticleCategoriesAction;
use App\Filament\Resources\ArticleResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;
use pxlrbt\FilamentExcel\Actions\Pages\ExportAction;
use pxlrbt\FilamentExcel\Columns\Column;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

class ListArticles extends ListRecords
{
    protected static string $resource = ArticleResource::class;

    protected function getActions(): array
    {
        return [
			// Export Stores csv
			ExportAction::make()
				->exports([
					ExcelExport::make()
						->label('Export Articles (CSV)')
						->withColumns([
							Column::make('id')->heading('article_id'),
							Column::make('title')->heading('article_title'),
							Column::make('categories.name')
								->heading('category_names')
								->getStateUsing(fn($record) => $record->categories->pluck('name')->join(',')),
						])
						->withFilename(fn($resource) => $resource::getModelLabel() . '-' . date('Y-m-d'))
						->withWriterType(\Maatwebsite\Excel\Excel::CSV)
				]),

            Actions\CreateAction::make(),

			SyncArticleCategoriesAction::make(),
		];
    }
}
