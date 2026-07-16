<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route middleware `role:` — restricts a route to one or more UserRole values,
 * e.g. `role:FSO` or `role:FSO,TRANSPORT`. Compares against the User's role enum.
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(401, 'Unauthenticated.');
        }

        // $user->role is a UserRole enum (cast); compare by its string value.
        $current = $user->role?->value;

        if ($current === null || ! in_array($current, $roles, true)) {
            abort(403, 'Your role is not permitted to perform this action.');
        }

        return $next($request);
    }
}
