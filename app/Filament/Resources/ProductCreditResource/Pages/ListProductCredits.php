<?php

namespace App\Filament\Resources\ProductCreditResource\Pages;

use App\Filament\Resources\ProductCreditResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProductCredits extends ListRecords
{
    protected static string $resource = ProductCreditResource::class;

    protected function getActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
