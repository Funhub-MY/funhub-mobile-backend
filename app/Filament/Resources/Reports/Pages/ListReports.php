<?php

namespace App\Filament\Resources\Reports\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\Reports\ReportResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListReports extends ListRecords
{
    protected static string $resource = ReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
