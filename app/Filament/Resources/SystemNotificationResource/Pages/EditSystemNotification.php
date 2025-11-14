<?php

namespace App\Filament\Resources\SystemNotificationResource\Pages;

use App\Filament\Resources\SystemNotificationResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSystemNotification extends EditRecord
{
    protected static string $resource = SystemNotificationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

	protected function afterSave() {
		$this->emit('refreshRelation');
	}
}
