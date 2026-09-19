<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: [
            __DIR__.'/../routes/web.php',
            __DIR__.'/../routes/admin.php',
        ],
        api: __DIR__.'/../routes/api.php',
        channels: __DIR__.'/../routes/channels.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
            'admin_only' => \App\Http\Middleware\AdminOnlyMiddleware::class,
            'super_admin' => \App\Http\Middleware\SuperAdminMiddleware::class,
            'vendor' => \App\Http\Middleware\VendorMiddleware::class,
            'vendor.approved' => \App\Http\Middleware\EnsureVendorApproved::class,
            'customer' => \App\Http\Middleware\CustomerMiddleware::class,
            'auth.optional' => \App\Http\Middleware\OptionalSanctumAuth::class,
            'locale' => \App\Http\Middleware\SetLocale::class,
            'admin.locale' => \App\Http\Middleware\SetAdminLocale::class,
        ]);

        // Apply admin.locale EARLY in the web group (before auth middleware)
        $middleware->prependToGroup('web', 'admin.locale');
        $middleware->prependToGroup('api', 'locale');
        $middleware->prependToGroup('web', 'locale');
    })
    ->withProviders([
        \App\Providers\AppServiceProvider::class,
        \App\Providers\AuthServiceProvider::class,
    ])
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
