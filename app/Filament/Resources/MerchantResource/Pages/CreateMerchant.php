<?php

namespace App\Filament\Resources\MerchantResource\Pages;

use App\Models\User;
use App\Models\Merchant;
use Filament\Pages\Actions;
use Illuminate\Support\Str;
use Filament\Resources\Pages\CreateRecord;
use App\Notifications\MerchantOnboardEmail;
use App\Filament\Resources\MerchantResource;

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

        $data['default_password'] = Str::random(8);

        return $data;
    }

    protected function afterCreate(): void
    {
        $name = $this->record->name;
        $email = $this->record->email;
        $password = $this->record->default_password;

        $user = new User([
            'name' => $name,
            'email' => $email,
            'password' => bcrypt($password),
        ]);

        $user->save();

        $this->record->user_id = $user->id;
        $this->record->save();

        $user->notify(new MerchantOnboardEmail($name, $email, $password, $this->record->redeem_code));
    }
}
