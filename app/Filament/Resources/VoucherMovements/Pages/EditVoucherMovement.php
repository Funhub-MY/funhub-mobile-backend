<?php

namespace App\Filament\Resources\VoucherMovements\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\VoucherMovements\VoucherMovementResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditVoucherMovement extends EditRecord
{
    protected static string $resource = VoucherMovementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
