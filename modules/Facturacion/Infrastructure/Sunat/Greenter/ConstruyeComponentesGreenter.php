<?php

declare(strict_types=1);

namespace Modules\Facturacion\Infrastructure\Sunat\Greenter;

use Greenter\Model\Client\Client;
use Greenter\Model\Company\Address;
use Greenter\Model\Company\Company;
use Greenter\Model\Sale\Legend;
use Greenter\Model\Sale\SaleDetail;
use Luecano\NumeroALetras\NumeroALetras;
use Modules\Facturacion\Domain\Comprobante\Comprobante;
use Modules\Facturacion\Domain\Comprobante\ItemComprobante;
use Modules\Facturacion\Domain\Empresa\DatosEmisor;
use Modules\Facturacion\Domain\ValueObjects\Dinero;

trait ConstruyeComponentesGreenter
{
    private const NOMBRES_MONEDA = [
        'PEN' => 'SOLES',
        'USD' => 'DOLARES AMERICANOS',
    ];

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
