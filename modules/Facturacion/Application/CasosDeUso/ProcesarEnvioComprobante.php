<?php

declare(strict_types=1);

namespace Modules\Facturacion\Application\CasosDeUso;

use Modules\Facturacion\Domain\Comprobante\Comprobante;
use Modules\Facturacion\Domain\Comprobante\EstadoComprobante;
use Modules\Facturacion\Domain\Excepciones\ComprobanteInvalidoException;
use Modules\Facturacion\Domain\Puertos\AlmacenPrivado;
use Modules\Facturacion\Domain\Puertos\DespachadorWebhooks;
use Modules\Facturacion\Domain\Puertos\FabricaEnviadorComprobante;
use Modules\Facturacion\Domain\Puertos\GeneradorXmlFirmado;
use Modules\Facturacion\Domain\Puertos\ProveedorDatosSunat;
use Modules\Facturacion\Domain\Puertos\RegistradorTrazabilidadComprobante;
use Modules\Facturacion\Domain\Puertos\RepositorioComprobante;
use RuntimeException;
use Throwable;

final class ProcesarEnvioComprobante
{
    public function __construct(
        private readonly RepositorioComprobante $repositorio,
        private readonly ProveedorDatosSunat $proveedorDatosSunat,
        private readonly GeneradorXmlFirmado $generadorXmlFirmado,
        private readonly FabricaEnviadorComprobante $fabricaEnviador,
        private readonly AlmacenPrivado $almacen,
        private readonly RegistradorTrazabilidadComprobante $trazabilidad,
        private readonly DespachadorWebhooks $webhooks,
    ) {}

    public function ejecutar(string $empresaId, string $comprobanteId, string $entorno): void
    {
        $comprobante = $this->repositorio->buscarPorId($empresaId, $comprobanteId);

        if ($comprobante === null) {
            throw new ComprobanteInvalidoException("No existe el comprobante {$comprobanteId} para la empresa {$empresaId}.");
        }

        if (! $this->iniciarProcesamiento($comprobante)) {
            return;
        }

        $this->repositorio->actualizarEstado($comprobante);

        $xmlSha256 = null;
        $rutaXml = null;
        $rutaCdr = null;
        $resultado = null;
        $intento = $comprobante->intentosEnvio() + 1;
        $inicio = hrtime(true);

        try {
            $this->trazabilidad->registrarEvento($comprobante, 'PROCESANDO', actor: 'WORKER');
            $datosSunat = $this->proveedorDatosSunat->paraEmpresa($empresaId, $entorno);
            $comprobanteReferenciado = $this->resolverComprobanteReferenciado($comprobante);
            $xmlFirmado = $this->generadorXmlFirmado->generar(
                $comprobante,
                $datosSunat->emisor,
                $datosSunat->certificado,
                $comprobanteReferenciado,
            );
            $xmlSha256 = hash('sha256', $xmlFirmado);
            $rutaBase = $this->rutaBase($comprobante);
            $rutaXml = "{$rutaBase}/comprobante.xml";
            $this->almacen->guardar($rutaXml, $xmlFirmado);

            $enviador = $this->fabricaEnviador->crear($datosSunat);
            $resultado = $enviador->enviar($comprobante, $xmlFirmado);

            if (! $resultado->huboRespuestaSunat) {
                throw new RuntimeException($resultado->errorTecnico ?? 'SUNAT no devolvió una respuesta.');
            }

            $cdrSha256 = null;

            if ($resultado->cdrZipBase64 !== null && $resultado->cdrZipBase64 !== '') {
                $cdrBinario = base64_decode($resultado->cdrZipBase64, true) ?: '';
                $rutaCdr = "{$rutaBase}/cdr.zip";
                $this->almacen->guardar($rutaCdr, $cdrBinario);
                $cdrSha256 = hash('sha256', $cdrBinario);
            }

            match (true) {
                $resultado->esAceptado() => $comprobante->marcarAceptado(),
                $resultado->esAceptadoConObservaciones() => $comprobante->marcarAceptadoConObservaciones(),
                $resultado->esRechazado() => $comprobante->marcarRechazado(),
                default => throw new RuntimeException('SUNAT respondió con un código no reconocido: '.$resultado->codigoRespuesta),
            };

            $this->repositorio->actualizarEstado($comprobante, xmlSha256: $xmlSha256, cdrSha256: $cdrSha256);
            $this->trazabilidad->registrarEnvio(
                $comprobante,
                $entorno,
                $intento,
                $resultado,
                $rutaXml,
                $rutaCdr,
                $this->duracionMs($inicio),
            );
            $this->trazabilidad->registrarEvento($comprobante, $comprobante->estado()->value, actor: 'WORKER');
            $this->webhooks->despacharEventoTerminal($comprobante);
        } catch (Throwable $e) {
            if ($comprobante->estado() === EstadoComprobante::Procesando) {
                $comprobante->marcarError($e->getMessage());
                $this->repositorio->actualizarEstado($comprobante, xmlSha256: $xmlSha256);
                $this->trazabilidad->registrarEnvio(
                    $comprobante,
                    $entorno,
                    $intento,
                    $resultado,
                    $rutaXml,
                    $rutaCdr,
                    $this->duracionMs($inicio),
                    $e->getMessage(),
                );
                $this->trazabilidad->registrarEvento(
                    $comprobante,
                    'ERROR',
                    actor: 'WORKER',
                    datos: ['mensaje' => $e->getMessage(), 'intento' => $intento],
                );
                $this->webhooks->despacharEventoTerminal($comprobante);
            }

            throw $e;
        }
    }

    private function duracionMs(int $inicio): int
    {
        return (int) round((hrtime(true) - $inicio) / 1_000_000);
    }

    /**
     * @return bool false si el comprobante está en un estado terminal (no
     *              hay nada que hacer — un reintento del Job no debe
     *              reprocesar algo ya ACEPTADO/RECHAZADO).
     */
    private function iniciarProcesamiento(Comprobante $comprobante): bool
    {
        if ($comprobante->estado() === EstadoComprobante::Registrado) {
            $comprobante->marcarProcesando();

            return true;
        }

        if ($comprobante->esReintentable()) {
            $comprobante->reintentar();

            return true;
        }

        return false;
    }

    private function resolverComprobanteReferenciado(Comprobante $comprobante): ?Comprobante
    {
        $referencia = $comprobante->referencia();

        if ($referencia === null) {
            return null;
        }

        $original = $this->repositorio->buscarPorId($comprobante->empresaId(), $referencia->comprobanteId());

        if ($original === null) {
            throw new ComprobanteInvalidoException(
                "El comprobante de referencia {$referencia->comprobanteId()} ya no existe para la empresa {$comprobante->empresaId()}.",
            );
        }

        return $original;
    }

    private function rutaBase(Comprobante $comprobante): string
    {
        $fecha = $comprobante->fechaEmision();

        return sprintf(
            'empresas/%s/comprobantes/%s/%s/%s',
            $comprobante->empresaId(),
            $fecha->format('Y'),
            $fecha->format('m'),
            $comprobante->id(),
        );
    }
}
