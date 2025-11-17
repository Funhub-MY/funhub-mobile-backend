<?php

namespace App\Filament\Resources\VoucherTransfers\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\VoucherTransfers\VoucherTransferResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListVoucherTransfers extends ListRecords
{
    protected static string $resource = VoucherTransferResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
