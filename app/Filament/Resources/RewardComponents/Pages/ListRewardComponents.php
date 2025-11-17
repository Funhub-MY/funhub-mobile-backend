<?php

namespace App\Filament\Resources\RewardComponents\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\RewardComponents\RewardComponentResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRewardComponents extends ListRecords
{
    protected static string $resource = RewardComponentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
