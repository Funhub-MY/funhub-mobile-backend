<?php

namespace App\Filament\Resources\VoucherMovementResource\Pages;

use App\Filament\Resources\VoucherMovementResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditVoucherMovement extends EditRecord
{
    protected static string $resource = VoucherMovementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
