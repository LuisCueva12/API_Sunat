<?php

declare(strict_types=1);

namespace App\Http\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class RespuestaApi
{
    /**
     * @param  array<string, mixed>  $metaExtra
     */
    public static function exito(mixed $data, int $status = 200, array $metaExtra = []): JsonResponse
    {
        return new JsonResponse([
            'data' => $data,
            'meta' => array_merge(['request_id' => self::requestId()], $metaExtra),
        ], $status);
    }

    /**
     * @param  array<int, array<string, mixed>>  $detalles
     */
    public static function error(string $codigo, string $mensaje, int $status, array $detalles = []): JsonResponse
    {
        return new JsonResponse([
            'error' => [
                'codigo' => $codigo,
                'mensaje' => $mensaje,
                'detalles' => $detalles,
            ],
        ], $status);
    }

    private static function requestId(): string
    {
        /** @var Request|null $request */
        $request = app()->bound(Request::class) ? app(Request::class) : null;

        return $request?->attributes->get('request_id') ?? 'sin-request-id';
    }
}
