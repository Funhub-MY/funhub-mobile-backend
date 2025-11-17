<?php

namespace App\Filament\Resources\VoucherTransfers\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\VoucherTransfers\VoucherTransferResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditVoucherTransfer extends EditRecord
{
    protected static string $resource = VoucherTransferResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
