<?php

namespace App\Filament\Resources\ArticleResource\Pages;

use App\Filament\Actions\SyncArticleCategoriesAction;
use App\Filament\Resources\ArticleResource;
use App\Models\Article;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;
use pxlrbt\FilamentExcel\Actions\Pages\ExportAction;
use pxlrbt\FilamentExcel\Columns\Column;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

class ListArticles extends ListRecords
{
    protected static string $resource = ArticleResource::class;

    protected function getHeaderActions(): array
    {
        return [
			// Export Articles csv
			ExportAction::make()
				->exports([
					ExcelExport::make()
						->label('Export Articles (CSV)')
						->withColumns([
							Column::make('id')->heading('article_id'),
							Column::make('user_id')
								->heading('author')
								->getStateUsing(fn($record) => $record->user ? $record->user->name : 'N/A'),
							Column::make('lang')->heading('language'),
							Column::make('status')
								->heading('status')
								->getStateUsing(fn($record) => match($record->status) {
									Article::STATUS_DRAFT => 'Draft',
									Article::STATUS_PUBLISHED => 'Published',
									Article::STATUS_ARCHIVED => 'Archived',
									default => 'Unknown'
								}),
							Column::make('hidden_from_home')
								->heading('hide from home')
								->getStateUsing(fn($record) => $record->hidden_from_home ? 'Yes' : 'No'),
							Column::make('categories.name')
								->heading('category')
								->getStateUsing(fn($record) => $record->categories->pluck('name')->join(',')),
							Column::make('subCategories.name')
								->heading('sub_categories')
								->getStateUsing(fn($record) => $record->subCategories->pluck('name')->join(',')),
							Column::make('tags.name')
								->heading('tags')
								->getStateUsing(fn($record) => $record->tags->pluck('name')->join(',')),
							Column::make('title')->heading('title'),
							Column::make('slug')->heading('slug'),
							Column::make('body')->heading('body')
						])
						->withChunkSize(100)
						->withFilename(fn($resource) => $resource::getModelLabel() . '-' . date('Y-m-d'))
						->withWriterType(\Maatwebsite\Excel\Excel::CSV)
				]),

            Actions\CreateAction::make(),

			SyncArticleCategoriesAction::make(),
		];
    }
}
