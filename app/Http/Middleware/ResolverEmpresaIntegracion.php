<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Api\RespuestaApi;
use App\Models\Empresa;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Passport\Guards\TokenGuard;
use LogicException;
use Symfony\Component\HttpFoundation\Response;

final class ResolverEmpresaIntegracion
{
    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard('api');

        if (! $guard instanceof TokenGuard) {
            throw new LogicException("El guard 'api' debe estar configurado con el driver 'passport'.");
        }

        $cliente = $guard->client();

        if ($cliente === null) {
            return RespuestaApi::error(
                'NO_AUTORIZADO',
                'Falta el header Authorization: Bearer <access_token> o el token es inválido.',
                401,
            );
        }

        if ($cliente->revoked || $cliente->owner_type !== Empresa::class) {
            return RespuestaApi::error(
                'NO_AUTORIZADO',
                'La integración es inválida o fue revocada.',
                401,
            );
        }

        $cliente->newQuery()->whereKey($cliente->getKey())->update(['ultimo_uso_at' => now()]);

        $request->attributes->set('oauth_client', $cliente);
        $request->attributes->set('empresa_id', $cliente->owner_id);

        return $next($request);
    }
}
