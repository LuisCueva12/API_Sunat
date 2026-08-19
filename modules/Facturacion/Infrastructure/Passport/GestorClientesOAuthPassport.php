<?php

declare(strict_types=1);

namespace Modules\Facturacion\Infrastructure\Passport;

use App\Models\Empresa;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use Modules\Facturacion\Domain\Empresa\ResultadoClienteOAuth;
use Modules\Facturacion\Domain\Puertos\GestorClientesOAuth;

final class GestorClientesOAuthPassport implements GestorClientesOAuth
{
    public function crear(string $empresaId, string $nombre, array $scopes): ResultadoClienteOAuth
    {
        $secreto = Str::random(40);

        $cliente = Passport::client()->newQuery()->forceCreate([
            'name' => $nombre,
            'secret' => $secreto,
            'provider' => null,
            'redirect_uris' => [],
            'grant_types' => ['client_credentials'],
            'revoked' => false,
            'owner_type' => Empresa::class,
            'owner_id' => $empresaId,
            'scopes' => $scopes,
        ]);

        return new ResultadoClienteOAuth((string) $cliente->getKey(), $secreto);
    }

    public function revocar(string $oauthClientId): void
    {
        $cliente = Passport::client()->newQuery()->whereKey($oauthClientId)->firstOrFail();
        $cliente->update(['revoked' => true]);

        // Revocar el cliente no invalida por sí solo los access_token ya
        // emitidos (Passport solo valida revocación a nivel de token) — sin
        // esto, un token emitido antes de la revocación seguiría siendo
        // válido hasta su expiración natural (hasta 1 hora).
        $cliente->tokens()->update(['revoked' => true]);
    }
}
