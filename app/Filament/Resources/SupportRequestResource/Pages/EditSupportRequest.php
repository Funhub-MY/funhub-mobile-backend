<?php

namespace App\Filament\Resources\SupportRequestResource\Pages;

use App\Events\ClosedSupportTicket;
use App\Filament\Resources\SupportRequestResource;
use App\Models\SupportRequest;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSupportRequest extends EditRecord
{
    protected static string $resource = SupportRequestResource::class;

    protected function getActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    // afterSave
    protected function afterSave(): void
    {
        $record = $this->record;

        // fire event on ticket is closed
        if ($record->status == SupportRequest::STATUS_CLOSED) {
            event(new ClosedSupportTicket($record));
        }
    }
}
