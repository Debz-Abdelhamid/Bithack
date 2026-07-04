<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        // Private-channel authorization endpoint: authenticated + throttled
        // (Security.md §5 — limiter defined in AppServiceProvider).
        ['middleware' => ['web', 'auth', 'throttle:broadcasting-auth']],
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Non-panel authenticated routes (e.g. /broadcasting/auth) send guests
        // to the Filament login page — there is no framework-default "login" route.
        $middleware->redirectGuestsTo(fn (): string => route('filament.admin.auth.login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
