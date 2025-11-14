<?php

namespace App\Filament\Resources\CampaignRespondantDetailResource\Pages;

use App\Filament\Resources\CampaignRespondantDetailResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCampaignRespondantDetail extends EditRecord
{
    protected static string $resource = CampaignRespondantDetailResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
