<?php

namespace App\Filament\Resources\StoreResource\Pages;

use App\Filament\Actions\CustomImportStoresAction;
use App\Filament\Resources\StoreResource;
use App\Models\Country;
use App\Models\MerchantCategory;
use App\Models\State;
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
					// Allow user to upload state and country name in csv and convert to id by logic
					ImportField::make('state_name')
						->label('State Name')
						->required()
						->mutateBeforeCreate(function ($value) {
							$state = State::where('name', $value)->first();
							if (!$state) {
								throw new \Exception("State not found: {$value}");
							}
							return $state->id;
						}),
					ImportField::make('country_name')
						->label('Country Name')
						->required()
						->mutateBeforeCreate(function ($value) {
							$country = Country::where('name', $value)->first();
							if (!$country) {
								throw new \Exception("Country not found: {$value}");
							}
							return $country->id;
						}),
					ImportField::make('is_hq')
						->label('Is HQ?')
						->mutateBeforeCreate(function ($value) {
							$value = strtolower(trim($value));
							$truthyValues = ['yes', 'true', '1', 'active'];
							return in_array($value, $truthyValues) ? 1 : 0;
						}),
				])
				->mutateBeforeCreate(function ($data) {
					return [
						'name' => $data['name'],
						'address' => $data['address'],
						'address_postcode' => $data['address_postcode'] ?? null,
						'state_id' => $data['state_name'],
						'country_id' => $data['country_name'],
						'is_hq' => $data['is_hq'],
					];
				}),

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
