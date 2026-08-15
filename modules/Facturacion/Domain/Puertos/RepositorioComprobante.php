<?php

declare(strict_types=1);

namespace Modules\Facturacion\Domain\Puertos;

use Modules\Facturacion\Domain\Comprobante\Comprobante;

interface RepositorioComprobante
{
    public function guardar(Comprobante $comprobante): void;

    public function buscarPorId(string $empresaId, string $id): ?Comprobante;
}
