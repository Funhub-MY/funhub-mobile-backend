<?php

namespace App\Http\Middleware;

use Closure;
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
                
                return redirect()->route('filament.auth.login')->with('error', 'You have been logged out due to unauthorized access.');
            }
        }

        return $response;
    }
}
