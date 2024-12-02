<?php
namespace App\Http\Middleware;

use Closure;
use App\Models\Application;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class ValidateApplicationToken
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->bearerToken()) {
            return response()->json(['message' => 'No API token provided'], 401);
        }

        $token = PersonalAccessToken::findToken($request->bearerToken());

        if (!$token || !($token->tokenable instanceof Application)) {
            return response()->json(['message' => 'Invalid API token'], 401);
        }

        // check if the application is active
        if (!$token->tokenable->status) {
            return response()->json(['message' => 'Application is inactive'], 403);
        }

        return $next($request);
    }
}
