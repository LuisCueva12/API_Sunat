<?php

declare(strict_types=1);

namespace Modules\Facturacion\Infrastructure\Sunat\Greenter;

use Greenter\Model\Client\Client;
use Greenter\Model\Company\Address;
use Greenter\Model\Company\Company;
use Greenter\Model\Sale\FormaPagos\FormaPagoContado;
use Greenter\Model\Sale\Invoice;
use Greenter\Model\Sale\Legend;
use Greenter\Model\Sale\SaleDetail;
use LogicException;
use Luecano\NumeroALetras\NumeroALetras;
use Modules\Facturacion\Domain\Comprobante\Comprobante;
use Modules\Facturacion\Domain\Comprobante\ItemComprobante;
use Modules\Facturacion\Domain\Comprobante\TipoComprobante;
use Modules\Facturacion\Domain\Empresa\DatosEmisor;
use Modules\Facturacion\Domain\ValueObjects\Dinero;

final class MapeadorFacturaBoletaGreenter
{
    private const TIPOS_DOC = [
        TipoComprobante::Factura->value => '01',
        TipoComprobante::Boleta->value => '03',
    ];

    private const TIPO_OPERACION_VENTA_INTERNA = '0101';

    private const NOMBRES_MONEDA = [
        'PEN' => 'SOLES',
        'USD' => 'DOLARES AMERICANOS',
    ];

    public function mapear(Comprobante $comprobante, DatosEmisor $emisor): Invoice
    {
        $totales = $comprobante->totales();

        if ($totales === null) {
            throw new LogicException('No se puede mapear a Greenter un comprobante sin totales calculados.');
        }

        $tipoDoc = self::TIPOS_DOC[$comprobante->tipo()->value]
            ?? throw new LogicException("MapeadorFacturaBoletaGreenter no soporta el tipo '{$comprobante->tipo()->value}'.");

        $invoice = new Invoice;
        $invoice->setUblVersion('2.1')
            ->setTipoDoc($tipoDoc)
            ->setTipoOperacion(self::TIPO_OPERACION_VENTA_INTERNA)
            ->setSerie($comprobante->numero()->serie()->valor())
            ->setCorrelativo((string) $comprobante->numero()->correlativo())
            ->setFechaEmision($comprobante->fechaEmision())
            ->setTipoMoneda($comprobante->moneda()->value)
            ->setFormaPago(new FormaPagoContado)
            ->setCompany($this->mapearEmisor($emisor))
            ->setClient($this->mapearReceptor($comprobante))
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

        return $invoice;
    }

    private function mapearEmisor(DatosEmisor $emisor): Company
    {
        $company = new Company;
        $company->setRuc($emisor->ruc->valor())
            ->setRazonSocial($emisor->razonSocial)
            ->setNombreComercial($emisor->nombreComercial);

        $address = new Address;
        $address->setDireccion($emisor->direccion)
            ->setUbigueo($emisor->ubigeo)
            ->setCodLocal($emisor->codigoLocal)
            ->setDepartamento($emisor->departamento)
            ->setProvincia($emisor->provincia)
            ->setDistrito($emisor->distrito);
        $company->setAddress($address);

        return $company;
    }

    private function mapearReceptor(Comprobante $comprobante): Client
    {
        $client = new Client;
        $client->setTipoDoc($comprobante->receptorDocumento()->tipo()->value)
            ->setNumDoc($comprobante->receptorDocumento()->numero())
            ->setRznSocial($comprobante->receptorRazonSocial());

        return $client;
    }

    private function mapearItem(ItemComprobante $item): SaleDetail
    {
        $detalle = new SaleDetail;
        $detalle->setCodProducto($item->codigoProducto())
            ->setUnidad($item->unidadMedida())
            ->setCantidad($item->cantidad())
            ->setDescripcion($item->descripcion())
            ->setMtoValorUnitario($this->float($item->valorUnitario()))
            ->setMtoValorVenta($this->float($item->montoValorVenta()))
            ->setMtoBaseIgv($this->float($item->montoValorVenta()))
            ->setPorcentajeIgv(18.0)
            ->setIgv($this->float($item->montoIgv()))
            ->setTipAfeIgv($item->tipoAfectacionIgv())
            ->setTotalImpuestos($this->float($item->montoIgv()))
            ->setMtoPrecioUnitario($this->float($item->precioUnitario()));

        return $detalle;
    }

    private function construirLeyendaMontoEnLetras(Comprobante $comprobante, Dinero $total): Legend
    {
        $nombreMoneda = self::NOMBRES_MONEDA[$comprobante->moneda()->value];

        $legend = new Legend;
        $legend->setCode('1000')
            ->setValue((new NumeroALetras)->toInvoice($this->float($total), 2, $nombreMoneda));

        return $legend;
    }

    private function float(Dinero $dinero): float
    {
        return (float) $dinero->comoString();
    }
}
