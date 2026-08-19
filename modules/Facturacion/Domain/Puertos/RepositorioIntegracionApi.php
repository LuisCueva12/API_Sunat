<?php

declare(strict_types=1);

namespace Modules\Facturacion\Domain\Puertos;

use Modules\Facturacion\Domain\Empresa\IntegracionApi;

interface RepositorioIntegracionApi
{
    public function guardar(IntegracionApi $integracion): void;

    public function buscarPorId(string $empresaId, string $id): ?IntegracionApi;
}
