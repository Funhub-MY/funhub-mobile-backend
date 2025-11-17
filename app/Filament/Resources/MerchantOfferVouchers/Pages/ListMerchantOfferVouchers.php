<?php

namespace App\Filament\Resources\MerchantOfferVouchers\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\MerchantOfferVouchers\MerchantOfferVoucherResource;
use App\Models\MerchantOfferClaim;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;
use pxlrbt\FilamentExcel\Actions\Pages\ExportAction;
use pxlrbt\FilamentExcel\Columns\Column;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

class ListMerchantOfferVouchers extends ListRecords
{
    protected static string $resource = MerchantOfferVoucherResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
