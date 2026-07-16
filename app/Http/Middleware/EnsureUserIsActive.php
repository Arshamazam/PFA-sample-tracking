<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rejects requests from users whose account has been deactivated (is_active=false),
 * even if they still hold a valid token. Applied to all authenticated routes so a
 * disabled account is locked out immediately without needing token revocation.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user->is_active) {
            // Proactively revoke the presented token so the client stops retrying.
            $token = $user->currentAccessToken();
            if ($token !== null && method_exists($token, 'delete')) {
                $token->delete();
            }

            abort(403, 'Your account has been deactivated.');
        }

        return $next($request);
    }
}
