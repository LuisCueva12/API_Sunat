<?php

declare(strict_types=1);

namespace Modules\Facturacion\Domain\Puertos;

use Modules\Facturacion\Domain\Comprobante\TipoComprobante;
use Modules\Facturacion\Domain\Empresa\SerieEmpresa;
use Modules\Facturacion\Domain\ValueObjects\Serie;

interface RepositorioSerie
{
    public function guardar(SerieEmpresa $serie): void;

    public function existe(string $empresaId, TipoComprobante $tipo, Serie $serie): bool;
}
