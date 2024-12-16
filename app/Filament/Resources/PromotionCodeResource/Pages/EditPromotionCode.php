<?php

namespace App\Filament\Resources\PromotionCodeResource\Pages;

use App\Filament\Resources\PromotionCodeResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPromotionCode extends EditRecord
{
    protected static string $resource = PromotionCodeResource::class;

    protected function getActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
