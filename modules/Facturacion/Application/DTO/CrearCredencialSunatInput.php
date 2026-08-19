<?php

declare(strict_types=1);

namespace Modules\Facturacion\Application\DTO;

final class CrearCredencialSunatInput
{
    public function __construct(
        public readonly string $empresaId,
        public readonly string $entorno,
        public readonly string $usuarioSol,
        public readonly string $claveSol,
    ) {}
}
