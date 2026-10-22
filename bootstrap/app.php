<?php

use App\Http\Middleware\AdminOnly;
use App\Http\Middleware\ApiAuditMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(append: [ApiAuditMiddleware::class]);
        $middleware->alias(['admin' => AdminOnly::class]);
    })
    ->withCommands()
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
