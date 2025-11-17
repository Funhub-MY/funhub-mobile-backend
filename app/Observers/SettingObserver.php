<?php

namespace App\Observers;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class SettingObserver
{
    /**
     * Handle the Setting "created" event.
     *
     * @param Setting $setting
     * @return void
     */
    public function created(Setting $setting)
    {
        $this->bustSettingCache($setting);
    }

    /**
     * Handle the Setting "updated" event.
     *
     * @param Setting $setting
     * @return void
     */
    public function updated(Setting $setting)
    {
        $this->bustSettingCache($setting);
    }

    /**
     * Handle the Setting "deleted" event.
     *
     * @param Setting $setting
     * @return void
     */
    public function deleted(Setting $setting)
    {
        $this->bustSettingCache($setting);
    }

    /**
     * Handle the Setting "restored" event.
     *
     * @param Setting $setting
     * @return void
     */
    public function restored(Setting $setting)
    {
        $this->bustSettingCache($setting);
    }

    /**
     * Handle the Setting "force deleted" event.
     *
     * @param Setting $setting
     * @return void
     */
    public function forceDeleted(Setting $setting)
    {
        $this->bustSettingCache($setting);
    }

    /**
     * Bust the cache for the setting
     *
     * @param Setting $setting
     * @return void
     */
    private function bustSettingCache(Setting $setting): void
    {
        Cache::forget('setting_' . $setting->key);
    }
}
