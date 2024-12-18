<?php

namespace App\Filament\Resources\PromotionCodeGroupResource\Pages;

use App\Filament\Resources\PromotionCodeGroupResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPromotionCodeGroup extends EditRecord
{
    protected static string $resource = PromotionCodeGroupResource::class;

    protected function getActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
