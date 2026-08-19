<?php

declare(strict_types=1);

namespace Modules\Facturacion\Domain\Puertos;

use Modules\Facturacion\Domain\Empresa\ResultadoClienteOAuth;

interface GestorClientesOAuth
{
    /**
     * @param  array<int, string>  $scopes
     */
    public function crear(string $empresaId, string $nombre, array $scopes): ResultadoClienteOAuth;

    public function revocar(string $oauthClientId): void;
}
