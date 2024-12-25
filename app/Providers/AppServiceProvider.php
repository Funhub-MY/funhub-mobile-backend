<?php

namespace App\Providers;

use App\Filament\Macros\TranslationsMacro;
use App\Models\ArticleFeedWhitelistUser;
use App\Models\MerchantOffer;
use Filament\Facades\Filament;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\HtmlString;
use Illuminate\Support\ServiceProvider;

use Cheesegrits\FilamentGoogleMaps\Helpers\MapsHelper as OriginalMapsHelper;
use App\MapsHelper;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        if ($this->app->environment('local')) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            $this->app->register(TelescopeServiceProvider::class);
        }

        $this->app->bind(OriginalMapsHelper::class, MapsHelper::class);
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        MerchantOffer::observe(\App\Observers\MerchantOfferObserver::class);
        ArticleFeedWhitelistUser::observe(\App\Observers\ArticleFeedWhitelistUserObserver::class);

        // TextInput::macro('translations', function ($fieldName) {
        //     $locales = config('app.available_locales');

        //     return $this->reactive()
        //         ->afterStateUpdated(function ($state, callable $set) use ($fieldName, $locales) {
        //             foreach ($locales as $locale => $language) {
        //                 $set("{$fieldName}_translations.{$locale}", $state);
        //             }
        //         })
        //         ->hint(function (callable $get, callable $set) use ($locales, $fieldName) {
        //             $currentLocale = $get('current_locale');
        //             $options = collect($locales)
        //                 ->map(function ($language, $locale) use ($currentLocale) {
        //                     $selected = $locale === $currentLocale ? 'selected' : '';
        //                     return "<option value=\"{$locale}\" {$selected}>{$language}</option>";
        //                 })
        //                 ->implode('');

        //             return new HtmlString("
        //                 <div class=\"flex items-center\">
        //                     <select class=\"filament-forms-input text-xs px-4 text-left py-1\" wire:model=\"current_locale\" wire:change=\"\$emit('localeChanged')\" class=\"language-select\">
        //                         {$options}
        //                     </select>
        //                 </div>
        //             ");
        //         })
        //         ->reactive()
        //         ->afterStateHydrated(function ($component, $state, callable $set, $record, callable $get) use ($fieldName, $locales) {
        //             if ($record) {
        //                 $translations = json_decode($record->{$fieldName . '_translations'} ?? '{}', true);
        //                 foreach ($locales as $locale => $language) {
        //                     $set("{$fieldName}_translations.{$locale}", $translations[$locale] ?? $record->{$fieldName});
        //                 }
        //                 $currentLocale = $get('current_locale') ?? array_key_first($locales);
        //                 $set('current_locale', $currentLocale);
        //                 $set($fieldName, $translations[$currentLocale] ?? $record->{$fieldName});
        //             } else {
        //                 foreach ($locales as $locale => $language) {
        //                     $set("{$fieldName}_translations.{$locale}", $component->getState()[$fieldName] ?? '');
        //                 }
        //                 $currentLocale = $get('current_locale') ?? array_key_first($locales);
        //                 $set('current_locale', $currentLocale);
        //                 $set($fieldName, $component->getState()[$fieldName] ?? '');
        //             }
        //         })
        //         ->dehydrated(false)
        //         ->dehydrateStateUsing(function ($state, $component) use ($fieldName, $locales) {
        //             $translations = [];
        //             foreach ($locales as $locale => $language) {
        //                 $translations[$locale] = $component->getState()["{$fieldName}_translations.{$locale}"] ?? '';
        //             }
        //             return json_encode($translations);
        //         })
        //         ->registerListeners([
        //             "localeChanged" => [
        //                 function ($component, $state, $get, $set) use ($fieldName) {
        //                     dd('test');
        //                     $currentLocale = $get('current_locale');
        //                     $translationKey = "{$fieldName}_translations.{$currentLocale}";
        //                     $set($fieldName, $get($translationKey) ?? '');
        //                 },
        //             ],
        //         ]);
        // });

        Filament::registerRenderHook(
            name: 'scripts.start',
            callback: fn () => new HtmlString(html: "
                <script>
                    document.addEventListener('DOMContentLoaded', function(){
                       let sidebar_item = document.querySelector('.filament-sidebar-item-active');
                               if( sidebar_item ) {
                                    sidebar_item.scrollIntoView({ behavior: \"auto\", block: \"center\", inline: \"center\" });
                               }
                    });
                </script>
        "));


        if ($this->app->environment('production')) {
            // if filament is serving
            Filament::serving(function () {
                // so assets load by https for filament
                URL::forceScheme('https');
            });
        }
    }
}
