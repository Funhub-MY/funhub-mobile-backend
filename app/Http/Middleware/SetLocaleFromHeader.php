<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Jobs\UpdateUserLastLang;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Queue;
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
        $supportedLocales = ['en', 'zh'];
        if (in_array($locale, $supportedLocales)) {
            // Set the locale for the current request
            App::setLocale($locale);

            // If the user is authenticated, save the preferred language in the session
            if (Auth::check()) {
                Session::put('locale', $locale);

                // Dispatch a job to update the last_lang column
                UpdateUserLastLang::dispatch(Auth::user(), $locale);
            }
        }

        return $next($request);
    }
}
