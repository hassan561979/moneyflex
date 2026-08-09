<?php

use App\Exceptions\ApiExceptionRenderer;
use App\Http\Middleware\AuthenticateWithBasicAuth;
use App\Http\Middleware\AuthenticateWithBasicOrJwt;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            // The scheme the brief requires, on its own.
            'auth.basic.api' => AuthenticateWithBasicAuth::class,
            // The same, plus bearer tokens for clients that prefer them.
            'auth.api' => AuthenticateWithBasicOrJwt::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // One JSON shape for every failure, so no client ever has to parse an
        // HTML error page or guess at the response body.
        $exceptions->render(ApiExceptionRenderer::handle(...));
    })->create();
