<?php

declare(strict_types=1);

namespace Modules\Facturacion\Domain\Empresa;

use Modules\Facturacion\Domain\ValueObjects\Ruc;

/**
 * Datos del emisor necesarios para construir un comprobante hacia SUNAT.
 * Deliberadamente separado de Comprobante: el emisor es responsabilidad de
 * Empresa, no del agregado Comprobante (ver docs/01_ARQUITECTURA.md — el
 * snapshot_emisor es un detalle de persistencia, no un dato que el dominio
 * del comprobante necesite cargar).
 */
final class DatosEmisor
{
    public function __construct(
        public readonly Ruc $ruc,
        public readonly string $razonSocial,
        public readonly ?string $nombreComercial,
        public readonly ?string $direccion,
        public readonly ?string $ubigeo,
    ) {}
}
