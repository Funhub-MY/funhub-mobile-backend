<?php

namespace App\Filament\Resources\Faqs\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\Faqs\FaqResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFaq extends EditRecord
{
    protected static string $resource = FaqResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
