<?php

namespace App\Filament\Resources\ReservationFormFieldResource\Pages;

use App\Filament\Resources\ReservationFormFieldResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewReservationFormField extends ViewRecord
{
    protected static string $resource = ReservationFormFieldResource::class;

    protected function getActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}

