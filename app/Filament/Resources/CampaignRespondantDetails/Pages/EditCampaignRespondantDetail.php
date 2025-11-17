<?php

namespace App\Filament\Resources\CampaignRespondantDetails\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\CampaignRespondantDetails\CampaignRespondantDetailResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCampaignRespondantDetail extends EditRecord
{
    protected static string $resource = CampaignRespondantDetailResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
