<?php

namespace App\Filament\Resources\Merchants\Pages;

use App\Models\User;
use App\Models\Merchant;
use Filament\Pages\Actions;
use Illuminate\Support\Str;
use Filament\Resources\Pages\CreateRecord;
use App\Notifications\MerchantOnboardEmail;
use App\Filament\Resources\Merchants\MerchantResource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

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

    protected function handleRecordCreation(array $data): Model
    {
        $merchant = $this->getModel()::create($data);

        // create menus
        if (isset($data['menus'])) {
            foreach ($data['menus'] as $menu) {
                // add from url to media collection with custom properties $menu['name'] then remove from file
                $merchant->addMediaFromDisk($menu['file'])
                    ->withCustomProperties(['name' => $menu['name']])
                    ->toMediaCollection(Merchant::MEDIA_COLLECTION_MENUS);

                // remove $menu['file'] from storage as moved to spatiemedialibrary
                Storage::delete($menu['file']);
            }
        }

        return $merchant;
    }

    protected function afterCreate(): void
    {
        $name = $this->record->name;
        $email = $this->record->email;
        $password = $this->record->default_password;

        $sameEmailUser = User::where('email', $email)->first();
        $user = null;
        if ($sameEmailUser) {
            // create user without email first
            Log::info('[CreateMerchant] User with same email already exists, creating user without email first', [
                'email' => $email,
                'name' => $name,
            ]);
            $user = new User([
                'name' => $name,
                'password' => bcrypt($password),
            ]);
            $user->save();
        } else {
            $user = new User([
                'name' => $name,
                'email' => $email,
                'password' => bcrypt($password),
            ]);
            $user->save();
        }
        // attach merchant role
        $user->assignRole('merchant');

        $this->record->user_id = $user->id;
        $this->record->save();

        // if user is approved only fire email\
        if ($this->record->status == Merchant::STATUS_APPROVED) {
            // $user->notify(new MerchantOnboardEmail($name, $email, $password, $this->record->redeem_code));
        }
    }
}
