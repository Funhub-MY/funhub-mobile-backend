<?php

namespace App\Filament\Resources\ApprovalSettings\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\ApprovalSettings\ApprovalSettingResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditApprovalSetting extends EditRecord
{
    protected static string $resource = ApprovalSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
