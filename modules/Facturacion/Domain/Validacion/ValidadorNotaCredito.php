<?php

declare(strict_types=1);

namespace Modules\Facturacion\Domain\Validacion;

use Modules\Facturacion\Domain\Comprobante\Comprobante;
use Modules\Facturacion\Domain\Comprobante\EstadoComprobante;
use Modules\Facturacion\Domain\Comprobante\TipoComprobante;
use Modules\Facturacion\Domain\Excepciones\ComprobanteInvalidoException;
use Modules\Facturacion\Domain\Puertos\RepositorioComprobante;

final class ValidadorNotaCredito implements ValidadorComprobante
{
    public function __construct(
        private readonly RepositorioComprobante $repositorio,
    ) {}

    public function validar(Comprobante $comprobante): void
    {
        if ($comprobante->tipo() !== TipoComprobante::NotaCredito) {
            throw new ComprobanteInvalidoException('ValidadorNotaCredito solo aplica a comprobantes de tipo NOTA_CREDITO.');
        }

        $referencia = $comprobante->referencia();

        if ($referencia === null) {
            throw new ComprobanteInvalidoException('Una nota de crédito requiere un comprobante de referencia.');
        }

        if (trim($referencia->descripcionMotivo()) === '') {
            throw new ComprobanteInvalidoException('El motivo de la nota de crédito es obligatorio.');
        }

        $original = $this->repositorio->buscarPorId($comprobante->empresaId(), $referencia->comprobanteId());

        if ($original === null) {
            throw new ComprobanteInvalidoException('El comprobante de referencia no existe o no pertenece a esta empresa.');
        }

        if (! in_array($original->tipo(), [TipoComprobante::Factura, TipoComprobante::Boleta], true)) {
            throw new ComprobanteInvalidoException('Una nota de crédito solo puede referenciar una factura o una boleta.');
        }

        if (! in_array($original->estado(), [EstadoComprobante::Aceptado, EstadoComprobante::AceptadoConObservaciones], true)) {
            throw new ComprobanteInvalidoException('Solo se puede emitir una nota de crédito sobre un comprobante ya aceptado por SUNAT.');
        }

        if (count($comprobante->items()) === 0) {
            throw new ComprobanteInvalidoException('Una nota de crédito debe tener al menos un item.');
        }

        $totales = $comprobante->totales();

        if ($totales === null || $totales->total->esCero() || $totales->total->esNegativo()) {
            throw new ComprobanteInvalidoException('El total de la nota de crédito debe ser mayor a cero.');
        }
    }
}
