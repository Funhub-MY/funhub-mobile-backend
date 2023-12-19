<?php

namespace App\Filament\Resources\CampaignRespondantDetailResource\Pages;

use App\Filament\Resources\CampaignRespondantDetailResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCampaignRespondantDetails extends ListRecords
{
    protected static string $resource = CampaignRespondantDetailResource::class;

    protected function getActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
