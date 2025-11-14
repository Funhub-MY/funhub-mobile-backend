<?php

namespace App\Filament\Resources\ApprovalSettingResource\Pages;

use App\Filament\Resources\ApprovalSettingResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditApprovalSetting extends EditRecord
{
    protected static string $resource = ApprovalSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
