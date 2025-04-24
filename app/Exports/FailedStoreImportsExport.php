<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Illuminate\Support\Collection;
use App\Models\FailedStoreImport;

class FailedStoreImportsExport implements FromCollection, WithHeadings, WithMapping
{
    protected $failedStoreImportIds;

    public function __construct(array $failedStoreImportIds = [])
    {
        $this->failedStoreImportIds = $failedStoreImportIds;
    }

    public function collection(): Collection
    {
        $query = FailedStoreImport::query();

        if (!empty($this->failedStoreImportIds)) {
            return $query->whereIn('id', $this->failedStoreImportIds)->get();
        }

        return $query->get();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Store Name',
            'Address',
            'Postcode',
            'City',
            'State ID',
            'Country ID',
            'Phone Number',
            'Latitude',
            'Longitude',
            'Google Place ID',
            'Merchant ID',
            'User ID',
            'Is HQ',
            'Is Appointment Only',
            'Parent Categories',
            'Sub Categories',
            'Failure Reason',
            'Created At'
        ];
    }

    public function map($row): array
    {
        return [
            $row->id,
            $row->name,
            $row->address,
            $row->address_postcode,
            $row->city,
            $row->state_id,
            $row->country_id,
            $row->business_phone_no,
            $row->lang,
            $row->long,
            $row->google_place_id,
            $row->merchant_id,
            $row->user_id,
            $row->is_hq ? 'Yes' : 'No',
            $row->is_appointment_only ? 'Yes' : 'No',
            $row->parent_categories,
            $row->sub_categories,
            $row->failure_reason,
            $row->created_at ? $row->created_at->format('Y-m-d H:i:s') : '',
        ];
    }
}
