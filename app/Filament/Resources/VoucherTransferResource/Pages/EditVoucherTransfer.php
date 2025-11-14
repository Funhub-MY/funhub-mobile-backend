<?php

namespace App\Filament\Resources\VoucherTransferResource\Pages;

use App\Filament\Resources\VoucherTransferResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditVoucherTransfer extends EditRecord
{
    protected static string $resource = VoucherTransferResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
