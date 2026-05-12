<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateLogout
{
    public function handle(Request $request, Closure $next)
    {

        $response = $next($request);

        // Ensure user is authenticated before logging out
        if ($response->getStatusCode() === Response::HTTP_FORBIDDEN) {

            // Prevent infinite redirect loop
            if (Auth::check()) {
                Auth::logout();
                $request->session()->invalidate();

                $redirect = new RedirectResponse(route('filament.auth.login'));
                $redirect->setSession($request->session());
                $redirect->setRequest($request);

                return $redirect->with('error', 'You have been logged out due to unauthorized access.');
            }
        }

        return $response;
    }
}
