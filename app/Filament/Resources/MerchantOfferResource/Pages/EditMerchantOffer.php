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

    protected function getActions(): array
    {
        return [
           // custom action show language dropdown config('app.available_locales)
           SelectAction::make('language')
                ->options(config('app.available_locales'))
                ->label('Select Language'),
            Actions\DeleteAction::make(),
        ];
    }

    public function updatedLanguage($value)
    {
        $this->dispatchBrowserEvent('language-changed', ['language' => $value]);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $this->language = $this->language ?? app()->getLocale();
        // if have language and gettranslatableatteributes, pre fill them in form based on selected language
        if ($this->language && $this->getResource()::getTranslatableAttributes()) {
            $language = $this->language;
            foreach ($this->getResource()::getTranslatableAttributes() as $attribute) {
                $data[$attribute] = json_decode($data[$attribute . '_translations'], true)[$language] ?? '';
            }
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $language = $this->language ?? app()->getLocale();

        foreach ($this->getResource()::getTranslatableAttributes() as $attribute) {
            $existingTranslations = json_decode($data[$attribute . '_translations'] ?? '{}', true);
            $existingTranslations[$language] = $data[$attribute];
            $data[$attribute . '_translations'] = json_encode($existingTranslations);
        }

        $data['currency'] = 'MYR';
        return $data;
    }


}
