<?php

declare(strict_types=1);

namespace Modules\Facturacion\Domain\Validacion;

use Modules\Facturacion\Domain\Comprobante\Comprobante;
use Modules\Facturacion\Domain\Comprobante\TipoComprobante;
use Modules\Facturacion\Domain\Excepciones\ComprobanteInvalidoException;
use Modules\Facturacion\Domain\Puertos\RepositorioComprobante;

final class ValidadorNotaDebito implements ValidadorComprobante
{
    public function __construct(
        private readonly RepositorioComprobante $repositorio,
    ) {}

    public function validar(Comprobante $comprobante): void
    {
        if ($comprobante->tipo() !== TipoComprobante::NotaDebito) {
            throw new ComprobanteInvalidoException('ValidadorNotaDebito solo aplica a comprobantes de tipo NOTA_DEBITO.');
        }

        $referencia = $comprobante->referencia();

        if ($referencia === null) {
            throw new ComprobanteInvalidoException('Una nota de débito requiere un comprobante de referencia.');
        }

        if (trim($referencia->descripcionMotivo()) === '') {
            throw new ComprobanteInvalidoException('El motivo de la nota de débito es obligatorio.');
        }

        $original = $this->repositorio->buscarPorId($comprobante->empresaId(), $referencia->comprobanteId());

        if ($original === null) {
            throw new ComprobanteInvalidoException('El comprobante de referencia no existe o no pertenece a esta empresa.');
        }

        if (! in_array($original->tipo(), [TipoComprobante::Factura, TipoComprobante::Boleta], true)) {
            throw new ComprobanteInvalidoException('Una nota de débito solo puede referenciar una factura o una boleta.');
        }

        if (count($comprobante->items()) === 0) {
            throw new ComprobanteInvalidoException('Una nota de débito debe tener al menos un item.');
        }

        $totales = $comprobante->totales();

        if ($totales === null || $totales->total->esCero() || $totales->total->esNegativo()) {
            throw new ComprobanteInvalidoException('El total de la nota de débito debe ser mayor a cero.');
        }
    }
}
