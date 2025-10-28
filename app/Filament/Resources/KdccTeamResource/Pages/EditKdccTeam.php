<?php

namespace App\Filament\Resources\KdccTeamResource\Pages;

use App\Filament\Resources\KdccTeamResource;
use Filament\Resources\Pages\EditRecord;
use Filament\Pages\Actions;

class EditKdccTeam extends EditRecord
{
    protected static string $resource = KdccTeamResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->before(function () {
                    if ($this->record->image) {
                        $imageService = new \App\Http\Controllers\Api\KdccController();
                        $imageService->deleteImage($this->record->image);
                    }
                }),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}