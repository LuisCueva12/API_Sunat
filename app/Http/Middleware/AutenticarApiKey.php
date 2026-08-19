<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Api\RespuestaApi;
use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Modules\Facturacion\Domain\Puertos\GeneradorClaveApi;
use Symfony\Component\HttpFoundation\Response;

final class AutenticarApiKey
{
    public function __construct(
        private readonly GeneradorClaveApi $generadorClaveApi,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if ($token === null || $token === '') {
            return RespuestaApi::error(
                'NO_AUTORIZADO',
                'Falta el header Authorization: Bearer <api_key>.',
                401,
            );
        }

        $apiKey = ApiKey::query()->where('hash', $this->generadorClaveApi->hash($token))->first();

        if ($apiKey === null || ! $apiKey->estaVigente()) {
            return RespuestaApi::error(
                'NO_AUTORIZADO',
                'La API Key es inválida, fue revocada o expiró.',
                401,
            );
        }

        $apiKey->forceFill(['ultimo_uso_at' => now()])->saveQuietly();

        $request->attributes->set('api_key', $apiKey);
        $request->attributes->set('empresa_id', $apiKey->empresa_id);

        return $next($request);
    }
}
