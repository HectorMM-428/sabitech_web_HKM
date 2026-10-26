<?php

namespace App\Http;

use App\Exceptions\BusinessRuleException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Convierte cualquier excepción de una ruta /api en el formato de error único del
 * contrato: { "error": { "code", "message", "fields" } }.
 *
 * - `code` es estable y en inglés: lo usa el frontend para decidir qué hacer.
 * - `message` es para mostrar al usuario, en español, y nunca incluye detalles
 *   internos (consultas, rutas de ficheros, trazas). Los detalles van al log.
 */
final class ApiErrorRenderer
{
    private const CODES_BY_STATUS = [
        401 => ['UNAUTHENTICATED', 'Tu sesión ha caducado. Vuelve a iniciar sesión.'],
        403 => ['FORBIDDEN', 'No tienes permiso para esta operación.'],
        404 => ['NOT_FOUND', 'No se ha encontrado el recurso.'],
        405 => ['METHOD_NOT_ALLOWED', 'Operación no permitida en esta dirección.'],
        419 => ['UNAUTHENTICATED', 'La sesión ha expirado. Recarga la página e inténtalo de nuevo.'],
        429 => ['TOO_MANY_REQUESTS', 'Demasiados intentos. Espera un minuto.'],
    ];

    public static function render(Throwable $e, Request $request): ?JsonResponse
    {
        if (! $request->is('api/*')) {
            return null;
        }

        if ($e instanceof HttpResponseException) {
            return null;
        }

        if ($e instanceof BusinessRuleException) {
            return self::response($e->status, $e->errorCode, $e->getMessage());
        }

        if ($e instanceof ValidationException) {
            return self::response(422, 'VALIDATION_FAILED', 'Revisa los campos marcados.', $e->errors());
        }

        if ($e instanceof AuthenticationException) {
            return self::response(401, ...self::CODES_BY_STATUS[401]);
        }

        if ($e instanceof HttpExceptionInterface) {
            $status = $e->getStatusCode();
            [$code, $message] = self::CODES_BY_STATUS[$status] ?? ['SERVER_ERROR', 'Ha ocurrido un error inesperado.'];

            return self::response($status, $code, $message)->withHeaders(
                array_intersect_key($e->getHeaders(), array_flip(['Retry-After', 'Allow']))
            );
        }

        report($e);

        return self::response(500, 'SERVER_ERROR', 'Ha ocurrido un error inesperado. Inténtalo de nuevo más tarde.');
    }

    /** @param array<string, array<int, string>>|null $fields */
    public static function response(int $status, string $code, string $message, ?array $fields = null): JsonResponse
    {
        return new JsonResponse([
            'error' => [
                'code' => $code,
                'message' => $message,
                'fields' => $fields,
            ],
        ], $status);
    }
}
