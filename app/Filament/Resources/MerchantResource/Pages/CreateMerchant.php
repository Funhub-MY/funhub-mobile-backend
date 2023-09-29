<?php

namespace App\Filament\Resources\MerchantResource\Pages;

use App\Filament\Resources\MerchantResource;
use App\Models\Merchant;
use Filament\Pages\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateMerchant extends CreateRecord
{
    protected static string $resource = MerchantResource::class;


    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // ensure redeem code is unique loop
        $maxTries = 0;
        $data['redeem_code'] = rand(100000, 999999);
        while (Merchant::where('redeem_code', $data['redeem_code'])->exists() && $maxTries < 10) {
            $data['redeem_code'] = rand(100000, 999999);
            $maxTries++;
        }
        return $data;
    }
}
