<?php

namespace App\Filament\Resources\RewardComponentResource\Pages;

use App\Filament\Resources\RewardComponentResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRewardComponents extends ListRecords
{
    protected static string $resource = RewardComponentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
