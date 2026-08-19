<?php

declare(strict_types=1);

namespace Modules\Facturacion\Domain\Puertos;

use Modules\Facturacion\Domain\Empresa\CredencialSunatEmpresa;
use Modules\Facturacion\Domain\Empresa\EntornoSunat;

interface RepositorioCredencialSunat
{
    public function guardar(CredencialSunatEmpresa $credencial): void;

    public function buscarPorEmpresaYEntorno(string $empresaId, EntornoSunat $entorno): ?CredencialSunatEmpresa;
}
