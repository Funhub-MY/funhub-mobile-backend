<?php

namespace App\Filament\Resources\CommentResource\Pages;

use App\Filament\Resources\CommentResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;
use pxlrbt\FilamentExcel\Actions\Pages\ExportAction;
use pxlrbt\FilamentExcel\Columns\Column;
use pxlrbt\FilamentExcel\Exports\ExcelExport;
use Maatwebsite\Excel\Excel;

class ListComments extends ListRecords
{
    protected static string $resource = CommentResource::class;

	protected function getHeaderActions(): array
	{
		return [
			Actions\CreateAction::make(),
			ExportAction::make()
				->exports([
					ExcelExport::make()
						->withColumns([
							Column::make('id')->heading('Comment Id'),
							Column::make('user_id')->heading('User Id'),
							Column::make('user.name')->heading('By User'),
							Column::make('parent_id')->heading('Parent Comment Id'),
							Column::make('reply_to_id')->heading('Reply to Comment Id'),
							Column::make('commentable_type')
								->heading('Commentable Type')
								->formatStateUsing(fn ($record) =>
								$record->commentable_type === 'App\Models\Article' ? 'Article' : $record->commentable_type
								),
							Column::make('commentable_id')
								->heading('Commentable Id')
								->formatStateUsing(fn ($record) =>
								$record->commentable_type === 'App\Models\Article' ? $record->commentable_id : null
								),
							Column::make('body')->heading('Comment'),
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
