<?php

declare(strict_types=1);

namespace Modules\Facturacion\Application\DTO;

final class CrearIntegracionApiInput
{
    /**
     * @param  array<int, string>  $scopes
     */
    public function __construct(
        public readonly string $empresaId,
        public readonly string $nombre,
        public readonly array $scopes,
    ) {}
}
