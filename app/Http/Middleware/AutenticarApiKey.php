<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Api\RespuestaApi;
use App\Models\ApiKey;
use App\Services\ApiKeys\GeneradorApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * empresa_id siempre se deriva de aquí — nunca se acepta como campo del
 * body/query de un request (evita IDOR entre tenants, ver docs/06
 * _SEGURIDAD.md). Los controladores solo deben leer
 * $request->attributes->get('empresa_id'), nunca confiar en el input.
 */
final class AutenticarApiKey
{
    public function __construct(
        private readonly GeneradorApiKey $generadorApiKey,
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

        $apiKey = ApiKey::query()->where('hash', $this->generadorApiKey->hash($token))->first();

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
