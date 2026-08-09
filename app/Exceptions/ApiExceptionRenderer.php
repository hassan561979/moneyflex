<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Turns any exception raised under /api into the same JSON envelope:
 *
 *     {"message": "...", "errors": {...}}
 *
 * The errors key is present only for validation failures, matching the shape
 * Laravel clients already expect.
 */
final class ApiExceptionRenderer
{
    public static function handle(Throwable $e, Request $request): ?JsonResponse
    {
        if (! $request->is('api/*')) {
            return null;
        }

        return match (true) {
            // Let Laravel render validation errors: the default body is
            // already the documented shape.
            $e instanceof ValidationException => null,

            $e instanceof AuthenticationException => self::json(
                'Unauthenticated.',
                Response::HTTP_UNAUTHORIZED,
            ),

            $e instanceof AuthorizationException => self::json(
                'This action is unauthorized.',
                Response::HTTP_FORBIDDEN,
            ),

            // Route model binding throws NotFoundHttpException; a manual
            // lookup throws ModelNotFoundException. Both mean the same thing
            // to a client, and neither should reveal the model class.
            $e instanceof ModelNotFoundException,
            $e instanceof NotFoundHttpException => self::json(
                'The requested resource was not found.',
                Response::HTTP_NOT_FOUND,
            ),

            $e instanceof HttpExceptionInterface => self::json(
                $e->getMessage() ?: 'An error occurred.',
                $e->getStatusCode(),
            ),

            default => self::unexpected($e),
        };
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private static function json(string $message, int $status, array $extra = []): JsonResponse
    {
        return response()->json(['message' => $message, ...$extra], $status);
    }

    /**
     * Anything unhandled is a server fault. The details are exposed only when
     * debugging, so production never leaks a stack trace or file path.
     */
    private static function unexpected(Throwable $e): JsonResponse
    {
        if (config('app.debug')) {
            return self::json($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR, [
                'exception' => $e::class,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }

        return self::json('Server error.', Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}
