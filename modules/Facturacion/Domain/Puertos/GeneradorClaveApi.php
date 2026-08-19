<?php

declare(strict_types=1);

namespace Modules\Facturacion\Domain\Puertos;

use Modules\Facturacion\Domain\Empresa\ResultadoClaveApi;

interface GeneradorClaveApi
{
    public function generar(): ResultadoClaveApi;

    public function hash(string $claveCompleta): string;
}
