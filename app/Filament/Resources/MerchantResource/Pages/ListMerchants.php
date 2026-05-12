<?php

namespace App\Filament\Resources\MerchantResource\Pages;

use App\Filament\Resources\MerchantResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMerchants extends ListRecords
{
    protected static string $resource = MerchantResource::class;

    protected static function shouldRegisterNavigation(): bool
    {
        return parent::shouldRegisterNavigation() && MerchantResource::canViewAny();
    }

    protected function getActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
