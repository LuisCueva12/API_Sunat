<?php

declare(strict_types=1);

namespace Modules\Facturacion\Application\CasosDeUso;

use Modules\Facturacion\Domain\Comprobante\Comprobante;
use Modules\Facturacion\Domain\Excepciones\ComprobanteNoEncontradoException;
use Modules\Facturacion\Domain\Excepciones\TransicionEstadoInvalidaException;
use Modules\Facturacion\Domain\Puertos\DespachadorProcesamiento;
use Modules\Facturacion\Domain\Puertos\RegistradorTrazabilidadComprobante;
use Modules\Facturacion\Domain\Puertos\RepositorioComprobante;

final class ReintentarComprobante
{
    public function __construct(
        private readonly RepositorioComprobante $repositorio,
        private readonly DespachadorProcesamiento $despachador,
        private readonly RegistradorTrazabilidadComprobante $trazabilidad,
    ) {}

    public function ejecutar(string $empresaId, string $comprobanteId, ?string $requestId = null): Comprobante
    {
        $comprobante = $this->repositorio->buscarPorId($empresaId, $comprobanteId);

        if ($comprobante === null) {
            throw new ComprobanteNoEncontradoException('El comprobante solicitado no existe.');
        }

        if (! $comprobante->esReintentable()) {
            throw new TransicionEstadoInvalidaException(
                "Solo se puede reintentar un comprobante en estado ERROR (estado actual: {$comprobante->estado()->value}).",
            );
        }

        $this->despachador->despacharEnvio($empresaId, $comprobanteId, $requestId);
        $this->trazabilidad->registrarEvento(
            $comprobante,
            'REINTENTO_PROGRAMADO',
            actor: 'INTEGRACION_O_USUARIO',
            requestId: $requestId,
        );

        return $comprobante;
    }
}
