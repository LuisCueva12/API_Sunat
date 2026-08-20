<?php

declare(strict_types=1);

namespace App\Services\Comprobantes;

use App\Models\Comprobante;
use Modules\Facturacion\Domain\Puertos\AlmacenPrivado;
use Modules\Facturacion\Domain\Puertos\GeneradorXmlFirmado;
use Modules\Facturacion\Domain\Puertos\ProveedorDatosSunat;
use Modules\Facturacion\Domain\Puertos\RepositorioComprobante;
use Throwable;

final class ObtenedorXmlFirmado
{
    /** @var list<string> */
    private const ENTORNOS = ['BETA', 'PRODUCCION'];

    public function __construct(
        private readonly AlmacenPrivado $almacen,
        private readonly RepositorioComprobante $repositorio,
        private readonly ProveedorDatosSunat $proveedorDatosSunat,
        private readonly GeneradorXmlFirmado $generadorXmlFirmado,
    ) {}

    public function obtener(Comprobante $comprobante, string $ruta): string
    {
        if ($this->almacen->existe($ruta)) {
            $xml = $this->almacen->leer($ruta);

            if ($this->esIntegro($xml, $comprobante->xml_sha256)) {
                return $xml;
            }
        }

        $xmlRecuperado = $this->recuperar($comprobante);

        if ($xmlRecuperado === null) {
            throw new XmlFirmadoNoDisponible('No se encontró una copia íntegra del XML firmado del comprobante.');
        }

        $this->almacen->guardar($ruta, $xmlRecuperado);

        return $xmlRecuperado;
    }

    private function recuperar(Comprobante $fila): ?string
    {
        if ($fila->xml_sha256 === null || $fila->xml_sha256 === '') {
            return null;
        }

        $comprobante = $this->repositorio->buscarPorId($fila->empresa_id, $fila->id);

        if ($comprobante === null) {
            return null;
        }

        $referencia = $comprobante->referencia();
        $comprobanteReferenciado = $referencia === null
            ? null
            : $this->repositorio->buscarPorId($fila->empresa_id, $referencia->comprobanteId());

        if ($referencia !== null && $comprobanteReferenciado === null) {
            return null;
        }

        foreach (self::ENTORNOS as $entorno) {
            try {
                $datosSunat = $this->proveedorDatosSunat->paraEmpresa($fila->empresa_id, $entorno);
                $xml = $this->generadorXmlFirmado->generar(
                    $comprobante,
                    $datosSunat->emisor,
                    $datosSunat->certificado,
                    $comprobanteReferenciado,
                );
            } catch (Throwable) {
                continue;
            }

            if ($this->esIntegro($xml, $fila->xml_sha256)) {
                return $xml;
            }
        }

        return null;
    }

    private function esIntegro(string $xml, ?string $hashEsperado): bool
    {
        if (trim($xml) === '') {
            return false;
        }

        return $hashEsperado === null
            || $hashEsperado === ''
            || hash_equals($hashEsperado, hash('sha256', $xml));
    }
}
