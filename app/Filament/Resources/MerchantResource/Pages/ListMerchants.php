<?php

namespace App\Filament\Resources\MerchantResource\Pages;

use App\Filament\Resources\MerchantResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMerchants extends ListRecords
{
    protected static string $resource = MerchantResource::class;

    protected static function shouldRegisterNavigation(): bool
    {
        dd('list here?');
        return auth()->user()->hasRole('super_admin');
    }

    public function mount(): void
    {
        abort_unless(auth()->user()->hasRole('super_admin'), 403);
    }

    protected function getActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
