<?php

namespace App\Filament\Resources\Campaigns\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\Campaigns\CampaignResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCampaigns extends ListRecords
{
    protected static string $resource = CampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
