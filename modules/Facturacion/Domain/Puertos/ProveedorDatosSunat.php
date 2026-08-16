<?php

declare(strict_types=1);

namespace Modules\Facturacion\Domain\Puertos;

use Modules\Facturacion\Domain\Empresa\DatosSunatEmpresa;
use Modules\Facturacion\Domain\Excepciones\ConfiguracionSunatInvalidaException;

interface ProveedorDatosSunat
{
    /**
     * @throws ConfiguracionSunatInvalidaException si la empresa no tiene certificado activo o credenciales para ese entorno
     */
    public function paraEmpresa(string $empresaId, string $entorno): DatosSunatEmpresa;
}
