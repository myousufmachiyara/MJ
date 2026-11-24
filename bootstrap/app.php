<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // Kept in its own file rather than appended to web.php, which
            // currently has unrelated pending edits — see routes/sync.php.
            require __DIR__.'/../routes/sync.php';
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'check.permission' => \App\Http\Middleware\CheckModulePermission::class,
        ]);

        // Global (web) — a no-op unless config('app.offline_mode') is true,
        // i.e. only on an offline-installer build. See its own docblock.
        $middleware->web(append: [
            \App\Http\Middleware\BlockOfflineRestrictedModules::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
