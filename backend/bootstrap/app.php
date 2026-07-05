<?php

use App\Support\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->throttleApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Every API error must follow the ApiResponse::error() contract:
        // {success, message, error: {code, details}}. Renderers are matched
        // in registration order; keep the Throwable catch-all last.
        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(
                message: 'Unauthenticated.',
                code: 'UNAUTHENTICATED',
                status: 401,
            );
        });

        $exceptions->render(function (ValidationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(
                message: 'Validation failed.',
                code: 'VALIDATION_ERROR',
                details: $exception->errors(),
                status: 422,
            );
        });

        // ModelNotFoundException and AuthorizationException are converted to
        // 404/403 HttpExceptions by the framework before renderers run, so a
        // single status-code map covers route binding misses, method
        // mismatches and throttling alike.
        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $status = $exception->getStatusCode();

            [$message, $code] = match ($status) {
                403 => ['This action is unauthorized.', 'FORBIDDEN'],
                404 => ['Resource not found.', 'NOT_FOUND'],
                405 => ['Method not allowed.', 'METHOD_NOT_ALLOWED'],
                429 => ['Too many requests. Please try again later.', 'TOO_MANY_REQUESTS'],
                default => [
                    $exception->getMessage() !== '' ? $exception->getMessage() : 'HTTP error.',
                    'HTTP_ERROR',
                ],
            };

            return ApiResponse::error(
                message: $message,
                code: $code,
                status: $status,
            )->withHeaders($exception->getHeaders()); // keep Retry-After & X-RateLimit-*
        });

        $exceptions->render(function (Throwable $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(
                message: config('app.debug') ? $exception->getMessage() : 'An unexpected error occurred.',
                code: 'SERVER_ERROR',
                status: 500,
            );
        });
    })->create();
