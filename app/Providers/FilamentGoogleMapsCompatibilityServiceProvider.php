<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class FilamentGoogleMapsCompatibilityServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Load compatibility interface early, before vendor autoloader
        // This ensures the interface exists before vendor packages try to use it
        // Must be loaded before composer autoloader processes vendor packages
        if (!interface_exists('Filament\Forms\Components\Contracts\HasAffixActions')) {
            require_once app_path('Compatibility/Filament/Forms/Components/Contracts/HasAffixActions.php');
        }
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}

