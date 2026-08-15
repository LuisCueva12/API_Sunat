<?php

declare(strict_types=1);

namespace Modules\Facturacion\Application\DTO;

final class EmitirComprobanteInput
{
    /**
     * @param  ItemInput[]  $items
     */
    public function __construct(
        public readonly string $empresaId,
        public readonly string $serie,
        public readonly string $receptorTipoDocumento,
        public readonly string $receptorNumeroDocumento,
        public readonly string $receptorRazonSocial,
        public readonly array $items,
        public readonly string $moneda = 'PEN',
    ) {}
}
