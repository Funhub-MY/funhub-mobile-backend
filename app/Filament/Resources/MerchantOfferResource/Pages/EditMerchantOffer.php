<?php

namespace App\Filament\Resources\MerchantOfferResource\Pages;

use App\Filament\Resources\MerchantOfferResource;
use Filament\Pages\Actions;
use Filament\Pages\Actions\SelectAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Pages\Actions\Action;
use Filament\Forms\Components\Select;

class EditMerchantOffer extends EditRecord
{
    protected static string $resource = MerchantOfferResource::class;
    public $language;
    public $current_locale;

    protected function getActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $language = $this->current_locale ?? app()->getLocale();

        foreach ($this->getResource()::getTranslatableAttributes() as $attribute) {
            $existingTranslations = json_decode($data[$attribute . '_translations'] ?? '{}', true);
            $existingTranslations[$language] = $data[$attribute];
            $data[$attribute . '_translations'] = json_encode($existingTranslations);
        }

        $data['currency'] = 'MYR';
        return $data;
    }


}
