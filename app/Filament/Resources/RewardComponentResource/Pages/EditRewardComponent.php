<?php

namespace App\Filament\Resources\RewardComponentResource\Pages;

use App\Filament\Resources\RewardComponentResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRewardComponent extends EditRecord
{
    protected static string $resource = RewardComponentResource::class;

    protected function getActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
