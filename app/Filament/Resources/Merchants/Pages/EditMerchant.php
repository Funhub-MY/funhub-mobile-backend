<?php

namespace App\Filament\Resources\Merchants\Pages;

use Filament\Actions\DeleteAction;
use App\Services\SyncMerchantPortal;
use App\Events\ClosedSupportTicket;
use App\Filament\Resources\Merchants\MerchantResource;
use App\Models\KocMerchantHistory;
use App\Models\Merchant;
use App\Models\SupportRequest;
use Filament\Forms\Components\FileUpload;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
class EditMerchant extends EditRecord
{
    protected static string $resource = MerchantResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
		$this->originalKocUserId = $data['koc_user_id'] ?? null;
		Log::info('Original KOC User ID: ', ['originalKocUserId' => $this->originalKocUserId]);

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
            // Save existing menu files to a temporary directory
            $tempDir = Storage::disk($disk)->path('temp/' . Str::random(10));
            Storage::disk($disk)->makeDirectory($tempDir);

            $existingMenus = [];
            foreach ($this->record->getMedia(Merchant::MEDIA_COLLECTION_MENUS) as $media) {
                $tempFilePath = $tempDir . '/' . $media->file_name;
                $fileContents = Storage::disk($disk)->get($media->getPath());
                Storage::disk($disk)->put($tempFilePath, $fileContents);
                $existingMenus[$media->file_name] = [
                    'path' => $tempFilePath,
                    'custom_properties' => $media->custom_properties,
                ];
            }

            foreach ($data['menus'] as $index => $menu) {
                $file = $menu['file'];

                // Check if the file exists in the temporary directory or on the storage disk
                if (isset($existingMenus[$file])) {
                    $filePath = $existingMenus[$file]['path'];
                    $customProperties = array_merge($existingMenus[$file]['custom_properties'], ['name' => $menu['name']]);
                } elseif (Storage::disk($disk)->exists($file)) {
                    $filePath = Storage::disk($disk)->path($file);
                    $customProperties = ['name' => $menu['name']];
                } else {
                    // Skip the file if it doesn't exist
                    Log::warning('File not found: ', ['file' => $file]);
                    continue;
                }

                $media = $this->record->addMediaFromDisk($filePath, $disk)
                    ->withCustomProperties($customProperties)
                    ->toMediaCollection(Merchant::MEDIA_COLLECTION_MENUS);

                Log::info('File uploaded: ', ['media' => $media, 'file' => $file]);

                // Delete the file from the temporary directory if it exists
                if (isset($existingMenus[$file])) {
                    Storage::disk($disk)->delete($existingMenus[$file]['path']);
                }

                // Set the order of the media item
                $media->order_column = $index + 1;
                $media->save();
            }

            // Delete the temporary directory
            Storage::deleteDirectory($tempDir);

            // Clear the media collection after processing all files
            $this->record->clearMediaCollection(Merchant::MEDIA_COLLECTION_MENUS);
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

	protected function afterSave(): void
	{
		$record = $this->record;

		$updatedKocUserId = $this->data['koc_user_id'] ?? null;

		if ($this->originalKocUserId !== $updatedKocUserId) {
			KocMerchantHistory::create([
				'merchant_id' => $record->id,
				'koc_user_id' => $updatedKocUserId,
			]);
		}

        //  Call the merchant portal api to sync (Send signal to merchant portal)
        $syncMerchantPortal = app(SyncMerchantPortal::class);
        $syncMerchantPortal->syncMerchant($record->id);
	}

}
