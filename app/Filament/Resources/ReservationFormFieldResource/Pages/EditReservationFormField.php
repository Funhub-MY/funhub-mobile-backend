<?php

namespace App\Filament\Resources\ReservationFormFieldResource\Pages;

use App\Filament\Resources\ReservationFormFieldResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditReservationFormField extends EditRecord
{
    protected static string $resource = ReservationFormFieldResource::class;

    protected function getActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}

