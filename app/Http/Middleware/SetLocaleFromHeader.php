<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Jobs\UpdateUserLastLang;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

class SetLocaleFromHeader
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        // Get the language from the request header
        $locale = $request->header('X-Locale');

		if (Auth::check()) {
			Log::info('Current Locale from header: ' . Auth::id() . ' : ' . $locale);
		}

        $supportedLocales = ['en', 'zh'];

        if (in_array($locale, $supportedLocales)) {
            // Set the locale for the current request
            App::setLocale($locale);
			Log::info('Current App Locale set as: ' . Auth::id() . ' : ' . $locale);
            // If the user is authenticated, handle language preference
            if (Auth::check()) {
                $user = Auth::user();
                $cacheKey = 'user_' . $user->id . '_last_lang_update';

                // Check if we need to update the user's language preference
                if ($this->shouldUpdateLanguage($user, $locale, $cacheKey)) {
                    // Update session
                    Session::put('locale', $locale);
                    UpdateUserLastLang::dispatch($user, $locale);
                    Cache::put($cacheKey, now(), now()->addMinutes(30));
                }
            }
        }

        return $next($request);
    }

    /**
     * Determine if we should update the user's language preference.
     *
     * @param  \App\Models\User  $user
     * @param  string  $locale
     * @param  string  $cacheKey
     * @return bool
     */
    private function shouldUpdateLanguage($user, $locale, $cacheKey)
    {
        // If the cache doesn't exist or has expired, we should update
        if (!Cache::has($cacheKey)) {
            return true;
        }
        if ($user->last_lang !== $locale) {
            return true;
        }
        return false;
    }
}
