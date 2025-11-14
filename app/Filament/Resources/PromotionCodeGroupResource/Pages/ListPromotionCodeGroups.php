<?php

namespace App\Filament\Resources\PromotionCodeGroupResource\Pages;

use App\Filament\Resources\PromotionCodeGroupResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPromotionCodeGroups extends ListRecords
{
    protected static string $resource = PromotionCodeGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
