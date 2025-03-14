<?php

namespace App\Filament\Resources\ReferralResource\Pages;

use App\Filament\Resources\ReferralResource;
use App\Models\User;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;
use Maatwebsite\Excel\Excel;
use pxlrbt\FilamentExcel\Actions\Pages\ExportAction;
use pxlrbt\FilamentExcel\Columns\Column;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

class ListReferrals extends ListRecords
{
    protected static string $resource = ReferralResource::class;
    public function getTitle(): string
    {
        return 'Referrals'; // Set the custom title for the page
    }
    protected function getActions(): array
    {
        return [
            Actions\CreateAction::make(),
			ExportAction::make()
				->exports([
					ExcelExport::make()
						->withColumns([
							Column::make('id')->heading('User Id'),
							Column::make('username')->heading('User'),
							Column::make('referral_total_number')
								->heading('Referral Total Number')
								->getStateUsing(function (User $record) {
									return $record->referrals()->count();
								}),
							Column::make('total_funbox_get')
								->heading('Total Funbox Get')
								->getStateUsing(function (User $record) {
									$latestLedger = $record->pointLedgers()->orderBy('id', 'desc')->first();
									return $latestLedger ? $latestLedger->balance : 0;
								}),
							Column::make('created_at')
								->heading('Created At')
								->formatStateUsing(fn ($state) => $state?->format('Y-m-d H:i:s')),
							Column::make('updated_at')
								->heading('Updated At')
								->formatStateUsing(fn ($state) => $state?->format('Y-m-d H:i:s')),
						])
						->withChunkSize(500)
						->withFilename(fn ($resource) => 'referrals-' . date('Y-m-d'))
						->withWriterType(Excel::CSV),
					])
        ];
    }
}
