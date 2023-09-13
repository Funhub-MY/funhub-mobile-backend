<?php

namespace App\Filament\Resources\MerchantResource\Pages;

use App\Filament\Resources\MerchantResource;
use App\Models\Merchant;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMerchant extends EditRecord
{
    protected static string $resource = MerchantResource::class;

    protected function getActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (!$data['redeem_code'] || $data['redeem_code'] == '') { // if no redeem_code
            // ensure redeem code is unique loop
            $maxTries = 0;
            $data['redeem_code'] = rand(100000, 999999);
            while (Merchant::where('redeem_code', $data['redeem_code'])->exists() && $maxTries < 10) {
                $data['redeem_code'] = rand(100000, 999999);
                $maxTries++;
            }
        }
        return $data;
    }
}
