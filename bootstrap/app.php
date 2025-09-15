<?php

use App\Exceptions\TaskConflictException;
use App\Exceptions\TaskNotDeletedException;
use App\Http\Middleware\ForceCacheControlHeader;
use App\Services\ApiResponseService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->api(append: [
            ForceCacheControlHeader::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (Throwable $e, Request $request) {
            $apiResponseService = app(ApiResponseService::class);

            if ($request->is('api/*')) {
                if ($e instanceof TaskConflictException) {
                    return $apiResponseService->error($e->getMessage(), $e->getCode());
                }

                if ($e instanceof TaskNotDeletedException) {
                    return $apiResponseService->error($e->getMessage(), $e->getCode());
                }

                if ($e instanceof ModelNotFoundException) {
                    return $apiResponseService->error('The requested resource was not found.', 404);
                }

                if ($e instanceof ValidationException) {
                    return $apiResponseService->error('The given data was invalid.', $e->status, $e->errors());
                }

                if ($e instanceof AuthenticationException) {
                    return $apiResponseService->error('Unauthenticated.', 401);
                }

                if ($e instanceof AccessDeniedHttpException) {
                    return $apiResponseService->error('This action is unauthorized.', 403);
                }

                if ($e instanceof AuthorizationException) {
                    return $apiResponseService->error('This action is unauthorized.', 403);
                }

                if ($e instanceof MethodNotAllowedHttpException) {
                    return $apiResponseService->error('Method not allowed.', 405);
                }

                // Default error for other exceptions
                return $apiResponseService->error('An unexpected error occurred.', 500);
            }
        });
    })->create();
