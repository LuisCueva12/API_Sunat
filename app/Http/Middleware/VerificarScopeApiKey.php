<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Api\RespuestaApi;
use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Debe ir después de AutenticarApiKey en la cadena de middleware — asume
 * que 'api_key' ya está resuelta en los attributes del request.
 */
final class VerificarScopeApiKey
{
    public function handle(Request $request, Closure $next, string $scope): Response
    {
        /** @var ApiKey|null $apiKey */
        $apiKey = $request->attributes->get('api_key');

        if ($apiKey === null || ! $apiKey->tieneScope($scope)) {
            return RespuestaApi::error(
                'PROHIBIDO',
                "Esta API Key no tiene el permiso requerido: {$scope}.",
                403,
            );
        }

        return $next($request);
    }
}
