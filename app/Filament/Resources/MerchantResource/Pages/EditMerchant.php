<?php

namespace App\Filament\Resources\MerchantResource\Pages;

use App\Filament\Resources\MerchantResource;
use App\Models\Merchant;
use Filament\Forms\Components\FileUpload;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
        $data['menus'] = $this->record->getMedia(Merchant::MEDIA_COLLECTION_MENUS)->map(function ($item, $index) {
            return [
                'name' => (isset($item->custom_properties['name'])) ? $item->custom_properties['name'] : 'Menu ' . ($index + 1),
                'file' => $item->getPath(),
            ];
        });

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Check if the email has changed
        // if ($this->record->email != $data['email']) {
        //     // If there are changes in the email, send a new verification email
        //     $user = $this->record->user;
        //     $user->email = $data['email'];
        //     $user->email_verified_at = null; // Mark email as unverified
        //     $user->save();

        //     // Send verification email
        //     $user->sendEmailVerificationNotification();
        // }

        // save uploads from repeater files
        if (isset($data['menus'])) {
            $disk = config('filesystems.default');
            if ($disk == 's3') {
                // use s3_public
                $disk = 's3_public';
            }

            Log::info('Menus: ', ['menus' => $data['menus']]);
            foreach ($data['menus'] as $menu) {
                $file = $menu['file'];
                $filePath = Storage::disk($disk)->path($file);

                // if already have existing media, instead of adding, just update it
                if ($this->record->getMedia(Merchant::MEDIA_COLLECTION_MENUS)->where('file_name', $file)->count() > 0) {
                    $this->record->getMedia(Merchant::MEDIA_COLLECTION_MENUS)->where('file_name', $file)->update(['custom_properties' => ['name' => $menu['name']]]);
                } else {
                    $media = $this->record->addMediaFromDisk($filePath, $disk)
                        ->withCustomProperties(['name' => $menu['name']])
                        ->toMediaCollection(Merchant::MEDIA_COLLECTION_MENUS);

                        Log::info('File uploaded: ', ['media' => $media, 'file' => $file]);

                        Storage::disk($disk)->delete($file);
                }
            }
        } else {
            $this->record->clearMediaCollection(Merchant::MEDIA_COLLECTION_MENUS);
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
