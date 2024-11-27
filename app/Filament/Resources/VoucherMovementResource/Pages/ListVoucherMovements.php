<?php

namespace App\Filament\Resources\VoucherMovementResource\Pages;

use App\Filament\Resources\VoucherMovementResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListVoucherMovements extends ListRecords
{
    protected static string $resource = VoucherMovementResource::class;

    protected function getActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
