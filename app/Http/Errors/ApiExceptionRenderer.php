<?php

namespace App\Http\Errors;

use App\Domain\Shared\Exceptions\BusinessRuleException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Renders every API error as {"code": "...", "message": "..."}.
 * Clients branch on `code`; `message` is translated and meant for humans.
 * Messages live in lang/*\/errors.php under the flat key `errors.<code>`.
 */
final class ApiExceptionRenderer
{
    public static function render(Throwable $e, Request $request): ?JsonResponse
    {
        if (! $request->is('api/*') && ! $request->expectsJson()) {
            return null;
        }

        // Responses that code built on purpose are returned as they are.
        if ($e instanceof HttpResponseException) {
            return null;
        }

        if ($e instanceof BusinessRuleException) {
            return self::error($e->errorCode, $e->status, $e->params);
        }

        if ($e instanceof ValidationException) {
            return self::error('validation.failed', 422, [], ['errors' => $e->errors()]);
        }

        if ($e instanceof AuthenticationException) {
            return self::error('http.unauthenticated', 401);
        }

        // Laravel already turned AuthorizationException and ModelNotFoundException
        // into 403 and 404 HTTP exceptions before this point.
        if ($e instanceof HttpExceptionInterface) {
            $status = $e->getStatusCode();
            $headers = $e->getHeaders();

            $code = match ($status) {
                403 => 'http.forbidden',
                404 => 'http.not_found',
                405 => 'http.method_not_allowed',
                429 => 'http.too_many_requests',
                default => 'http.error',
            };

            $params = $status === 429 ? ['seconds' => $headers['Retry-After'] ?? 60] : [];

            return self::error($code, $status, $params, [], $headers);
        }

        // Let developers see the real error locally.
        if (config('app.debug')) {
            return null;
        }

        return self::error('server.error', 500);
    }

    /**
     * @param  array<string, mixed>  $params
     * @param  array<string, mixed>  $extra
     * @param  array<string, mixed>  $headers
     */
    private static function error(string $code, int $status, array $params = [], array $extra = [], array $headers = []): JsonResponse
    {
        return new JsonResponse([
            'code' => $code,
            'message' => __("errors.{$code}", $params),
            ...$extra,
        ], $status, $headers);
    }
}
