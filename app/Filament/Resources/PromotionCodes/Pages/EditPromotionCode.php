<?php

namespace App\Filament\Resources\PromotionCodes\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\PromotionCodes\PromotionCodeResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPromotionCode extends EditRecord
{
    protected static string $resource = PromotionCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
