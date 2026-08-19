<?php

declare(strict_types=1);

namespace Modules\Facturacion\Domain\Puertos;

use Modules\Facturacion\Domain\Empresa\Empresa;

interface RepositorioEmpresa
{
    public function guardar(Empresa $empresa): void;

    public function buscarPorId(string $id): ?Empresa;

    public function buscarPorRuc(string $ruc): ?Empresa;
}
