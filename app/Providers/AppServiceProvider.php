<?php

namespace App\Providers;

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\HtmlString;
use Illuminate\Support\ServiceProvider;

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
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Filament::registerRenderHook(
            'panels::scripts.before',
            fn () => new HtmlString(html: "
                <script>
                    document.addEventListener('DOMContentLoaded', function(){
                        setTimeout(() => {
                            const activeSidebarItem = document.querySelector('.fi-sidebar-item-active');
                            const sidebarWrapper = document.querySelector('.fi-sidebar-nav');

                            sidebarWrapper.style.scrollBehavior = 'smooth';

                            sidebarWrapper.scrollTo(0, activeSidebarItem.offsetTop - 250);
                        }, 300)
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
