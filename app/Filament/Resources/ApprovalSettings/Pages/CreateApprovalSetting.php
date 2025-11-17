<?php

namespace App\Filament\Resources\ApprovalSettings\Pages;

use App\Filament\Resources\ApprovalSettings\ApprovalSettingResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateApprovalSetting extends CreateRecord
{
    protected static string $resource = ApprovalSettingResource::class;
}
