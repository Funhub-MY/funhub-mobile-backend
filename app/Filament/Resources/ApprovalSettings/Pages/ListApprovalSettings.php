<?php

namespace App\Filament\Resources\ApprovalSettings\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\ApprovalSettings\ApprovalSettingResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListApprovalSettings extends ListRecords
{
    protected static string $resource = ApprovalSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
