<?php

namespace App\Filament\Resources\KdccTeams\Pages;

use Filament\Actions\DeleteAction;
use App\Http\Controllers\Api\KdccController;
use App\Filament\Resources\KdccTeams\KdccTeamResource;
use Filament\Resources\Pages\EditRecord;
use Filament\Pages\Actions;

class EditKdccTeam extends EditRecord
{
    protected static string $resource = KdccTeamResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->before(function () {
                    if ($this->record->image) {
                        $imageService = new KdccController();
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