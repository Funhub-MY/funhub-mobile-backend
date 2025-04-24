<?php

namespace App\Exports;

use App\Models\FailedStoreImport;
use pxlrbt\FilamentExcel\Exports\ExcelExport;
use pxlrbt\FilamentExcel\Columns\Column;

class FailedStoreImportsExport extends ExcelExport
{
    public function setUp(): void
    {
        $this->withFilename('failed-store-imports-' . now()->format('Y-m-d'));
    }
    
    public function collection()
    {
        // Get records from the model directly without relying on filtered table query
        // This will export all records if no specific records are selected
        return FailedStoreImport::query()
            ->when(isset($this->ids) && !empty($this->ids), fn($query) => $query->whereIn('id', $this->ids))
            ->get();
    }

    public function columns(): array
    {
        return [
            Column::make('id')->heading('ID'),
            Column::make('name')->heading('Store Name'),
            Column::make('address')->heading('Address'),
            Column::make('address_postcode')->heading('Postcode'),
            Column::make('city')->heading('City'),
            Column::make('state_id')->heading('State ID'),
            Column::make('country_id')->heading('Country ID'),
            Column::make('business_phone_no')->heading('Phone Number'),
            Column::make('lang')->heading('Latitude'),
            Column::make('long')->heading('Longitude'),
            Column::make('google_place_id')->heading('Google Place ID'),
            Column::make('merchant_id')->heading('Merchant ID'),
            Column::make('user_id')->heading('User ID'),
            Column::make('is_hq')->heading('Is HQ')
                ->formatStateUsing(fn ($state) => $state ? 'Yes' : 'No'),
            Column::make('is_appointment_only')->heading('Is Appointment Only')
                ->formatStateUsing(fn ($state) => $state ? 'Yes' : 'No'),
            Column::make('parent_categories')->heading('Parent Categories'),
            Column::make('sub_categories')->heading('Sub Categories'),
            Column::make('failure_reason')->heading('Failure Reason'),
            Column::make('created_at')->heading('Created At')
                ->formatStateUsing(fn ($state) => $state ? $state->format('Y-m-d H:i:s') : ''),
        ];
    }
}
