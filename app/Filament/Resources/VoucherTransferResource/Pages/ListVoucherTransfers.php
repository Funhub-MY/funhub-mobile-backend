<?php

namespace App\Filament\Resources\VoucherTransferResource\Pages;

use App\Filament\Resources\VoucherTransferResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListVoucherTransfers extends ListRecords
{
    protected static string $resource = VoucherTransferResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
