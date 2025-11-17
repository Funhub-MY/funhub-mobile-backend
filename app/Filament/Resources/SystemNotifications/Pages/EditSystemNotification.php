<?php

namespace App\Filament\Resources\SystemNotifications\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\SystemNotifications\SystemNotificationResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSystemNotification extends EditRecord
{
    protected static string $resource = SystemNotificationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

	protected function afterSave() {
		$this->emit('refreshRelation');
	}
}
