<?php

namespace App\Filament\Resources\ProductCredits\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\ProductCredits\ProductCreditResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProductCredits extends ListRecords
{
    protected static string $resource = ProductCreditResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
