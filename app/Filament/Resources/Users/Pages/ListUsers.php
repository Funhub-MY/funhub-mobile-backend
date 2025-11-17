<?php

namespace App\Filament\Resources\Users\Pages;

use Filament\Actions\CreateAction;
use Maatwebsite\Excel\Excel;
use App\Filament\Resources\Users\UserResource;
use App\Models\Store;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;
use pxlrbt\FilamentExcel\Actions\Pages\ExportAction;
use pxlrbt\FilamentExcel\Columns\Column;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),

            // Export Stores csv
            ExportAction::make()
                ->exports([
                    ExcelExport::make()
                        ->label('Export Stores (CSV)')
                        ->withColumns([
                            Column::make('id')->heading('User Id'),
                            Column::make('name')->heading('Name'),
                            Column::make('username')->heading('User Name'),
                            Column::make('status')
                                ->heading('Status')
                                ->formatStateUsing(function ($record) {
                                    return match($record->status) {
                                        1 => 'Active',
                                        2 => 'Suspended',
                                        3 => 'Archived',
                                    };
                                }),
                            Column::make('phone_no')->heading('Phone Number'),
                            Column::make('email')->heading('Email'),
                            Column::make('email_verified_at')->heading('Email verify at'),
                            Column::make('profile_is_private')
                                ->heading('Profile Privacy')
                                ->formatStateUsing(function ($record) {
                                    return match($record->profile_is_private) {
                                        false => 'Public',
                                        true => 'Private',
                                    };
                                }),
                            Column::make('has_article_personalization')
                                ->heading('Has Article Personalization?')
                                ->formatStateUsing(function ($record) {
                                    return match($record->has_article_personalization) {
                                        0 => 'No',
                                        1 => 'Yes',
                                    };
                                }),
                            Column::make('referredBy.name')->heading('Referred By'),
                            Column::make('point_balance')->heading('Funhub Balance'),
                            Column::make('total_engagement')
                                ->formatStateUsing(fn($record) => $record->interactions()->count())
                                ->heading('Total Engagement'),
                            Column::make('created_at')->heading('Created at'),
                        ])
						->withChunkSize(500)
                        ->withFilename(fn ($resource) => $resource::getModelLabel() . '-' . date('Y-m-d'))
                        ->withWriterType(Excel::CSV)
                ]),
        ];
    }
}
