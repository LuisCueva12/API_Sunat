<?php

declare(strict_types=1);

namespace Modules\Facturacion\Domain\Puertos;

use Modules\Facturacion\Domain\Empresa\CertificadoEmpresa;

interface RepositorioCertificado
{
    public function guardar(CertificadoEmpresa $certificado): void;

    public function buscarActivoPorEmpresa(string $empresaId): ?CertificadoEmpresa;
}
