<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Previously logged the user out on any HTTP 403 response. That is incorrect:
 * 403 means "authenticated but not allowed for this action" (e.g. Filament policy / abort_unless),
 * not a broken session. Treating it as logout sent users back to the login page when opening
 * resources they were forbidden to see (e.g. Merchants list for non-super_admin).
 */
class AuthenticateLogout
{
    public function handle(Request $request, Closure $next)
    {
        return $next($request);
    }
}
