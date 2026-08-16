<?php

declare(strict_types=1);

namespace Modules\Facturacion\Application\CasosDeUso;

use Modules\Facturacion\Domain\Comprobante\Comprobante;
use Modules\Facturacion\Domain\Comprobante\EstadoComprobante;
use Modules\Facturacion\Domain\Excepciones\ComprobanteInvalidoException;
use Modules\Facturacion\Domain\Puertos\AlmacenPrivado;
use Modules\Facturacion\Domain\Puertos\FabricaEnviadorComprobante;
use Modules\Facturacion\Domain\Puertos\GeneradorXmlFirmado;
use Modules\Facturacion\Domain\Puertos\ProveedorDatosSunat;
use Modules\Facturacion\Domain\Puertos\RepositorioComprobante;
use Throwable;

/**
 * Genera XML, firma, guarda el artefacto, envía a SUNAT, interpreta la
 * respuesta y transiciona el estado — todo lo que EmitirFactura (y
 * hermanos) deja pendiente tras persistir en REGISTRADO. Deliberadamente
 * sin GestorTransacciones envolviendo todo esto: nunca una llamada de red
 * a SUNAT dentro de una transacción larga de BD (ver docs/01
 * _ARQUITECTURA.md §40) — cada actualizarEstado() es su propia operación
 * corta.
 *
 * No lee config() ni nada de Illuminate — Application solo depende de
 * Domain. $entorno lo resuelve quien invoca (el Job en app/Jobs).
 */
final class ProcesarEnvioComprobante
{
    public function __construct(
        private readonly RepositorioComprobante $repositorio,
        private readonly ProveedorDatosSunat $proveedorDatosSunat,
        private readonly GeneradorXmlFirmado $generadorXmlFirmado,
        private readonly FabricaEnviadorComprobante $fabricaEnviador,
        private readonly AlmacenPrivado $almacen,
    ) {}

    public function ejecutar(string $empresaId, string $comprobanteId, string $entorno): void
    {
        $comprobante = $this->repositorio->buscarPorId($empresaId, $comprobanteId);

        if ($comprobante === null) {
            throw new ComprobanteInvalidoException("No existe el comprobante {$comprobanteId} para la empresa {$empresaId}.");
        }

        if (! $this->iniciarProcesamiento($comprobante)) {
            return; // estado terminal o ya en curso: no hay nada que hacer
        }

        $this->repositorio->actualizarEstado($comprobante);

        try {
            $datosSunat = $this->proveedorDatosSunat->paraEmpresa($empresaId, $entorno);
            $xmlFirmado = $this->generadorXmlFirmado->generar($comprobante, $datosSunat->emisor, $datosSunat->certificado);
        } catch (Throwable $e) {
            $comprobante->marcarError($e->getMessage());
            $this->repositorio->actualizarEstado($comprobante);

            return;
        }

        $xmlSha256 = hash('sha256', $xmlFirmado);
        $rutaBase = $this->rutaBase($comprobante);
        $this->almacen->guardar("{$rutaBase}/comprobante.xml", $xmlFirmado);

        $enviador = $this->fabricaEnviador->crear($datosSunat);
        $resultado = $enviador->enviar($comprobante, $xmlFirmado);

        if (! $resultado->huboRespuestaSunat) {
            $comprobante->marcarError($resultado->errorTecnico);
            $this->repositorio->actualizarEstado($comprobante, xmlSha256: $xmlSha256);

            return;
        }

        $cdrSha256 = null;

        if ($resultado->cdrZipBase64 !== null && $resultado->cdrZipBase64 !== '') {
            $cdrBinario = base64_decode($resultado->cdrZipBase64, true) ?: '';
            $this->almacen->guardar("{$rutaBase}/cdr.zip", $cdrBinario);
            $cdrSha256 = hash('sha256', $cdrBinario);
        }

        match (true) {
            $resultado->esAceptado() => $comprobante->marcarAceptado(),
            $resultado->esAceptadoConObservaciones() => $comprobante->marcarAceptadoConObservaciones(),
            $resultado->esRechazado() => $comprobante->marcarRechazado(),
            default => $comprobante->marcarError('SUNAT respondió con un código no reconocido: '.$resultado->codigoRespuesta),
        };

        $this->repositorio->actualizarEstado($comprobante, xmlSha256: $xmlSha256, cdrSha256: $cdrSha256);
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
