<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Forces a web user flagged must_change_password onto the change-password screen
 * before they can reach any other panel page.
 */
class RedirectIfMustChangePassword
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $user->must_change_password
            && ! $request->routeIs('password.change', 'password.update', 'logout')) {
            return redirect()->route('password.change');
        }

        return $next($request);
    }
}
