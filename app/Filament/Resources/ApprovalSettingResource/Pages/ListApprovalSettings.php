<?php

namespace App\Filament\Resources\ApprovalSettingResource\Pages;

use App\Filament\Resources\ApprovalSettingResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListApprovalSettings extends ListRecords
{
    protected static string $resource = ApprovalSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
