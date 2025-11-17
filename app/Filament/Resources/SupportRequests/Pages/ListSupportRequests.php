<?php

namespace App\Filament\Resources\SupportRequests\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\SupportRequests\SupportRequestResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSupportRequests extends ListRecords
{
    protected static string $resource = SupportRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
