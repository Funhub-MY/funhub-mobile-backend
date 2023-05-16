<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\PersonalAccessToken;

class CheckStatus
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
        // check if user->status is User::STATUS_ACTIVE, else if suspended kick user out session
        if ($request->user()->status == User::STATUS_ACTIVE) {
            return $next($request);
        } else {
            $accessToken = $request->bearerToken();
            if ($accessToken) {
                // Get access token from database
                $token = PersonalAccessToken::findToken($accessToken);
                // Revoke token
                $token->delete();
            }

            abort(403);
        }
    }
}
