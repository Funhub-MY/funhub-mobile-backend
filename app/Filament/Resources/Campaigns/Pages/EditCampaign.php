<?php

namespace App\Filament\Resources\Campaigns\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\Campaigns\CampaignResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCampaign extends EditRecord
{
    protected static string $resource = CampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
