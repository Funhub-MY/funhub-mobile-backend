<?php

namespace App\Providers;

use App\Services\Mpay;
use Illuminate\Support\ServiceProvider;

class MpayServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Mpay::class, function ($app) {
            return new Mpay(
                mid: config('services.mpay.mid'),
                hashKey: config('services.mpay.hash_key'),
                fpxOrCardOnly: false
            );
        });
    }
}