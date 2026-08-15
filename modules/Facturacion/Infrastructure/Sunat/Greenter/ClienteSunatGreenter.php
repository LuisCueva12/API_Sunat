<?php

declare(strict_types=1);

namespace Modules\Facturacion\Infrastructure\Sunat\Greenter;

use Greenter\Model\Response\BillResult;
use Greenter\See;
use Modules\Facturacion\Domain\Comprobante\Comprobante;
use Modules\Facturacion\Domain\Comprobante\ResultadoEnvio;
use Modules\Facturacion\Domain\Puertos\EnviadorComprobanteElectronico;
use Throwable;

/**
 * Requiere ext-soap instalado (Greenter\Ws\Services\SoapClient extends
 * \SoapClient nativo) — confirmado leyendo el código fuente del paquete,
 * no asumido. Sin ext-soap esta clase falla al primer envío real, aunque
 * el resto de la aplicación (incluida la generación/firma de XML) funciona
 * igual sin ella.
 */
final class ClienteSunatGreenter implements EnviadorComprobanteElectronico
{
    public function __construct(
        private readonly string $usuarioSol,
        private readonly string $claveSol,
        private readonly string $endpoint,
    ) {}

    public function enviar(Comprobante $comprobante, string $xmlFirmado): ResultadoEnvio
    {
        $see = new See;
        $see->setService($this->endpoint);
        $see->setCredentials($this->usuarioSol, $this->claveSol);

        try {
            $resultado = $see->sendXmlFile($xmlFirmado);
        } catch (Throwable $e) {
            return ResultadoEnvio::errorTecnico($e->getMessage());
        }

        if ($resultado === null || ! $resultado->isSuccess()) {
            $mensaje = $resultado?->getError()?->getMessage() ?? 'SUNAT no devolvió una respuesta exitosa.';

            return ResultadoEnvio::errorTecnico($mensaje);
        }

        if (! $resultado instanceof BillResult) {
            return ResultadoEnvio::errorTecnico('Respuesta de SUNAT con formato inesperado (se esperaba BillResult).');
        }

        $cdr = $resultado->getCdrResponse();

        if ($cdr === null) {
            return ResultadoEnvio::errorTecnico('SUNAT respondió éxito pero sin CDR.');
        }

        return ResultadoEnvio::conRespuestaSunat(
            codigo: $cdr->getCode() ?? '',
            descripcion: $cdr->getDescription() ?? '',
            notas: $cdr->getNotes() ?? [],
            cdrZipBase64: base64_encode($resultado->getCdrZip() ?? ''),
        );
    }
}
