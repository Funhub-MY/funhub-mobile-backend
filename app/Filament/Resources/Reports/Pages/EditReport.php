<?php

namespace App\Filament\Resources\Reports\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\Reports\ReportResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditReport extends EditRecord
{
    protected static string $resource = ReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
