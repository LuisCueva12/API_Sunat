<?php

declare(strict_types=1);

namespace Modules\Facturacion\Infrastructure\Sunat\Greenter;

use Greenter\Model\Sale\Note;
use LogicException;
use Modules\Facturacion\Domain\Comprobante\Comprobante;
use Modules\Facturacion\Domain\Comprobante\TipoComprobante;
use Modules\Facturacion\Domain\Empresa\DatosEmisor;

final class MapeadorNotaGreenter
{
    use ConstruyeComponentesGreenter;

    private const TIPOS_DOC_NOTA = [
        TipoComprobante::NotaCredito->value => '07',
        TipoComprobante::NotaDebito->value => '08',
    ];

    private const TIPOS_DOC_AFECTADO = [
        TipoComprobante::Factura->value => '01',
        TipoComprobante::Boleta->value => '03',
    ];

    public function mapear(Comprobante $comprobante, DatosEmisor $emisor, Comprobante $comprobanteReferenciado): Note
    {
        $totales = $comprobante->totales();

        if ($totales === null) {
            throw new LogicException('No se puede mapear a Greenter un comprobante sin totales calculados.');
        }

        $referencia = $comprobante->referencia();

        if ($referencia === null) {
            throw new LogicException("La nota {$comprobante->id()} no tiene una referencia a un comprobante original.");
        }

        $tipoDoc = self::TIPOS_DOC_NOTA[$comprobante->tipo()->value]
            ?? throw new LogicException("MapeadorNotaGreenter no soporta el tipo '{$comprobante->tipo()->value}'.");

        $tipoDocAfectado = self::TIPOS_DOC_AFECTADO[$comprobanteReferenciado->tipo()->value]
            ?? throw new LogicException(
                "El comprobante referenciado '{$comprobanteReferenciado->tipo()->value}' no es un tipo de documento afectable válido."
            );

        $note = new Note;
        $note->setUblVersion('2.1')
            ->setTipoDoc($tipoDoc)
            ->setSerie($comprobante->numero()->serie()->valor())
            ->setCorrelativo((string) $comprobante->numero()->correlativo())
            ->setFechaEmision($comprobante->fechaEmision())
            ->setTipoMoneda($comprobante->moneda()->value)
            ->setCompany($this->mapearEmisor($emisor))
            ->setClient($this->mapearReceptor($comprobante))
            ->setCodMotivo($referencia->codigoMotivo())
            ->setDesMotivo($referencia->descripcionMotivo())
            ->setTipDocAfectado($tipoDocAfectado)
            ->setNumDocfectado(sprintf(
                '%s-%s',
                $comprobanteReferenciado->numero()->serie()->valor(),
                $comprobanteReferenciado->numero()->correlativo(),
            ))
            ->setMtoOperGravadas($this->float($totales->opGravada))
            ->setMtoOperInafectas($this->float($totales->opInafecta))
            ->setMtoOperExoneradas($this->float($totales->opExonerada))
            ->setMtoOperGratuitas($this->float($totales->opGratuita))
            ->setMtoIGV($this->float($totales->totalIgv))
            ->setTotalImpuestos($this->float($totales->totalIgv))
            ->setValorVenta($this->float($totales->opGravada))
            ->setSubTotal($this->float($totales->total))
            ->setMtoImpVenta($this->float($totales->total))
            ->setDetails(array_map($this->mapearItem(...), $comprobante->items()))
            ->setLegends([$this->construirLeyendaMontoEnLetras($comprobante, $totales->total)]);

        return $note;
    }
}
