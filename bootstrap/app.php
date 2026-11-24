<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Known workspace HTTP failures should use our branded UI even when
        // APP_DEBUG is enabled locally. JSON and validation redirects retain
        // Laravel's native behavior; exception details stay in server logs.
        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) {
            if ($request->expectsJson() || !($request->is('app') || $request->is('app/*'))) {
                return null;
            }

            $status = $exception->getStatusCode();
            if (!in_array($status, [403, 404, 419, 422, 429], true)) {
                return null;
            }

            return response()->view('errors.workspace', compact('status'), $status, [
                'Cache-Control' => 'no-store, private',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        });
    })->create();
