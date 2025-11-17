<?php

namespace App\Filament\Resources\RewardComponents\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\RewardComponents\RewardComponentResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRewardComponent extends EditRecord
{
    protected static string $resource = RewardComponentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
