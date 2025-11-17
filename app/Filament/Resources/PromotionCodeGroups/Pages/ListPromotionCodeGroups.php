<?php

namespace App\Filament\Resources\PromotionCodeGroups\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\PromotionCodeGroups\PromotionCodeGroupResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPromotionCodeGroups extends ListRecords
{
    protected static string $resource = PromotionCodeGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
