<?php

namespace App\Filament\Resources\StoreResource\Pages;

use App\Filament\Resources\StoreResource;
use App\Models\MerchantCategory;
use App\Models\Store;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Log;
use Konnco\FilamentImport\Actions\ImportField;
use Konnco\FilamentImport\Actions\ImportAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;
use pxlrbt\FilamentExcel\Actions\Pages\ExportAction;
use pxlrbt\FilamentExcel\Columns\Column;
use App\Filament\Actions\SyncStoreCategoriesAction;

class ListStores extends ListRecords
{
    protected static string $resource = StoreResource::class;

    protected function getActions(): array
    {
        return [
            Actions\CreateAction::make(),

			// Import Stores from CSV
			ImportAction::make('importStores')
				->label('Import Stores (csv)')
				->uniqueField('name')
				->fields([
					ImportField::make('name')
						->label('Store Name')
						->required(),
					ImportField::make('address')
						->label('Address')
						->required(),
					ImportField::make('address_postcode')
						->label('Postcode'),
					ImportField::make('state_id')
						->label('State ID'),
					ImportField::make('country_id')
						->label('Country ID'),
					ImportField::make('is_hq')
						->label('Is HQ?'),
				]),

			// Sync Stores Categories csv
			SyncStoreCategoriesAction::make(),

            // Export Stores csv
            ExportAction::make()
                ->exports([
                    ExcelExport::make()
                        ->label('Export Stores (CSV)')
                        ->withColumns([
                            Column::make('id')->heading('store_id'),
                            Column::make('name')->heading('store_name'),
                            Column::make('categories.name')
                                ->heading('category_names')
                                ->getStateUsing(fn ($record) => $record->categories->pluck('name')->join(',')),
                            Column::make('status')
                                ->heading('status')
                                ->getStateUsing(fn ($record) => Store::STATUS[$record->status]),
                        ])
                        ->withFilename(fn ($resource) => $resource::getModelLabel() . '-' . date('Y-m-d'))
                        ->withWriterType(\Maatwebsite\Excel\Excel::CSV)
                ]),
        ];
    }
}
