<?php

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
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo('/login');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (ValidationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => 'The given data was invalid.',
                'status' => 422,
                'errors' => $exception->errors(),
            ], 422);
        });
        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json(['message' => 'Unauthenticated.', 'status' => 401], 401);
        });
        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json(['message' => $exception->getMessage() ?: match ($exception->getStatusCode()) {
                403 => 'Forbidden.',
                404 => 'Not found.',
                default => 'Request failed.',
            }, 'status' => $exception->getStatusCode()], $exception->getStatusCode());
        });
        $exceptions->render(function (Throwable $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }
            $response = ['message' => 'Server error.', 'status' => 500];

            if (config('app.debug')) {
                $response['message'] = $exception->getMessage() ?: 'Server error.';
                $response['exception'] = class_basename($exception);
                $response['file'] = $exception->getFile();
                $response['line'] = $exception->getLine();
            }

            return response()->json($response, 500);
        });
    })->create();
