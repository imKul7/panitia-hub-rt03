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
        // Railway / Render terminate TLS before proxying the request to PHP.
        // Only accept the forwarded scheme (not client IP, host or port)
        // when the deliberately isolated preview runs behind that proxy.
        // For other controlled deployments, explicitly set TRUSTED_PROXIES.
        $proxies = env('TRUSTED_PROXIES', env('RT03_DEMO_MODE') === 'ephemeral' ? '*' : null);
        if (filled($proxies)) {
            $middleware->trustProxies(
                at: $proxies,
                headers: Request::HEADER_X_FORWARDED_PROTO,
            );
        }
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

            // For a rejected browser form POST/PATCH/DELETE, return to the
            // actual workspace screen with an actionable popup. Keep the
            // original HTTP status for direct/API requests without a trusted
            // same-origin workspace referrer (existing API tests still apply).
            $referer = $request->header('referer');
            $from = $referer ? parse_url($referer) : false;
            $target = parse_url($request->fullUrl());
            $sameOrigin = is_array($from)
                && ($from['scheme'] ?? '') === ($target['scheme'] ?? '')
                && ($from['host'] ?? '') === ($target['host'] ?? '')
                && ($from['port'] ?? null) === ($target['port'] ?? null);
            $path = $sameOrigin ? ($from['path'] ?? '') : '';
            $validReturn = $path === '/app' || str_starts_with($path, '/app/');
            if (in_array($status, [403, 404, 422], true)
                && !in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)
                && $sameOrigin && $validReturn && auth()->check()) {
                $reason = match ($status) {
                    403 => 'Jabatan atau periode akun Anda tidak memiliki izin untuk tindakan tersebut.',
                    404 => 'Data yang diminta sudah tidak ditemukan pada periode ini.',
                    default => $exception->getMessage() !== ''
                        ? $exception->getMessage()
                        : 'Data yang dikirim tidak sesuai dengan aturan atau kondisi terbaru.',
                };
                $suggestion = \App\Support\FormFeedback::rejectedSuggestion($status, $reason);
                $title = match ($status) {
                    403 => 'Tindakan tidak diizinkan',
                    404 => 'Data tidak ditemukan',
                    default => 'Tindakan belum dapat diproses',
                };
                return redirect()->to($referer)->with('rt03_rejection', compact('title', 'reason', 'suggestion'));
            }

            return response()->view('errors.workspace', compact('status'), $status, [
                'Cache-Control' => 'no-store, private',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        });
    })->create();
