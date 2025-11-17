<?php

namespace App\Filament\Resources\SupportRequests\Pages;

use Filament\Actions\DeleteAction;
use App\Events\ClosedSupportTicket;
use App\Filament\Resources\SupportRequests\SupportRequestResource;
use App\Models\SupportRequest;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSupportRequest extends EditRecord
{
    protected static string $resource = SupportRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
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
