<?php

declare(strict_types=1);

namespace Modules\Clientes\Application\DTO;

final class ActualizarClienteInput
{
    public function __construct(
        public readonly string $empresaId,
        public readonly string $clienteId,
        public readonly string $razonSocial,
        public readonly ?string $direccion = null,
        public readonly ?string $email = null,
    ) {}
}
