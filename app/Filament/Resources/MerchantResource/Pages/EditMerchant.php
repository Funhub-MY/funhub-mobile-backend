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


    protected function mutateFormDataBeforeFill(array $data): array
    {
        // make sure media menus is loaded as array to repeater
        $data['menus'] = $this->record->getMedia(Merchant::MEDIA_COLLECTION_MENUS)->map(function ($item) {
            return [
                'name' => (isset($item->custom_properties['name'])) ? $item->custom_properties['name'] : '',
                'file' => $item->getFullUrl(),
            ];
        });
        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Check if the email has changed
        if ($this->record->email != $data['email']) {
            // If there are changes in the email, send a new verification email
            $user = $this->record->user;
            $user->email = $data['email'];
            $user->email_verified_at = null; // Mark email as unverified
            $user->save();

            // Send verification email
            $user->sendEmailVerificationNotification();
        }

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
