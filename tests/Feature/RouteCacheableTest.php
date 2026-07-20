<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * `route:cache` fails if any route uses a Closure. This asserts every application
 * route is backed by a controller action, so config:cache && route:cache (run in
 * CI / on deploy) always succeed.
 */
class RouteCacheableTest extends TestCase
{
    public function test_no_application_route_uses_a_closure(): void
    {
        $closureRoutes = [];

        foreach (Route::getRoutes() as $route) {
            $uses = $route->getAction('uses');

            // Framework-registered internals are allowed — the health check and the
            // local-disk storage serve route (`storage/{path}`) are both handled by
            // route:cache, which we confirm succeeds on deploy (see docs/DEPLOY.md).
            if ($route->getName() === 'health'
                || $route->uri() === 'up'
                || str_starts_with($route->uri(), 'storage/')) {
                continue;
            }

            if ($uses instanceof \Closure) {
                $closureRoutes[] = $route->uri();
            }
        }

        $this->assertSame([], $closureRoutes, 'These routes use closures and would break route:cache: '.implode(', ', $closureRoutes));
    }
}
