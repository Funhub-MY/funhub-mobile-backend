<?php

namespace App\Filament\Resources\Referrals\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\Referrals\ReferralResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditReferral extends EditRecord
{
    protected static string $resource = ReferralResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
