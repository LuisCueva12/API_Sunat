<?php

declare(strict_types=1);

namespace Modules\Facturacion\Domain\Puertos;

use Modules\Facturacion\Domain\Empresa\ApiKeyEmpresa;

interface RepositorioApiKey
{
    public function guardar(ApiKeyEmpresa $apiKey): void;
}
