<?php

namespace App\Filament\Resources\VoucherMovements\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\VoucherMovements\VoucherMovementResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListVoucherMovements extends ListRecords
{
    protected static string $resource = VoucherMovementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
