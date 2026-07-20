<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Redirects insecure requests to HTTPS and adds HSTS. Env-gated via FORCE_HTTPS so
 * local HTTP development is unaffected; enable it in production.
 */
class ForceHttps
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('app.force_https') && ! $request->secure() && ! app()->runningUnitTests()) {
            return redirect()->secure($request->getRequestUri(), 301);
        }

        $response = $next($request);

        if (config('app.force_https')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
