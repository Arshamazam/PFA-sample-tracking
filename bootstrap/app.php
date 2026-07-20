<?php

use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\RedirectIfMustChangePassword;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

// PHP 8.5 (used locally) deprecates the PDO::MYSQL_* constants, which the
// framework's bundled base config still references while merging configuration —
// this happens before Laravel's error handler boots, so the notice would print
// straight into HTTP/CLI output. Our production target (PHP 8.2/8.3 on shared
// hosting) never triggers it, so we strip only E_DEPRECATED and keep every other
// error class intact. This runs before config loading in web, CLI, and tests.
error_reporting(error_reporting() & ~E_DEPRECATED);

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api_v1.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'role' => EnsureUserHasRole::class,
            'active' => EnsureUserIsActive::class,
            'password.changed' => RedirectIfMustChangePassword::class,
            'noindex' => \App\Http\Middleware\NoIndex::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
