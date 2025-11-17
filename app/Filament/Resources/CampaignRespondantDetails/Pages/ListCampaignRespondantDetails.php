<?php

namespace App\Filament\Resources\CampaignRespondantDetails\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\CampaignRespondantDetails\CampaignRespondantDetailResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCampaignRespondantDetails extends ListRecords
{
    protected static string $resource = CampaignRespondantDetailResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
