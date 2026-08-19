<?php

declare(strict_types=1);

namespace Modules\Facturacion\Application\CasosDeUso;

use DateTimeImmutable;
use Modules\Clientes\Domain\Puertos\RepositorioCliente;
use Modules\Clientes\Domain\TipoDocumentoCliente;
use Modules\Facturacion\Application\DTO\EmitirComprobanteInput;
use Modules\Facturacion\Domain\Comprobante\Comprobante;
use Modules\Facturacion\Domain\Comprobante\ReferenciaComprobante;
use Modules\Facturacion\Domain\Comprobante\TipoComprobante;
use Modules\Facturacion\Domain\Excepciones\ComprobanteInvalidoException;
use Modules\Facturacion\Domain\Puertos\AsignadorCorrelativo;
use Modules\Facturacion\Domain\Puertos\DespachadorProcesamiento;
use Modules\Facturacion\Domain\Puertos\GeneradorId;
use Modules\Facturacion\Domain\Puertos\GestorTransacciones;
use Modules\Facturacion\Domain\Puertos\RepositorioComprobante;
use Modules\Facturacion\Domain\Tributario\CalculadorTributos;
use Modules\Facturacion\Domain\Validacion\ValidadorComprobante;
use Modules\Facturacion\Domain\ValueObjects\Dinero;
use Modules\Facturacion\Domain\ValueObjects\DocumentoIdentidad;
use Modules\Facturacion\Domain\ValueObjects\Moneda;
use Modules\Facturacion\Domain\ValueObjects\Serie;
use Modules\Facturacion\Domain\ValueObjects\TipoDocumentoIdentidad;

abstract class EmitirComprobanteBase
{
    public function __construct(
        private readonly GestorTransacciones $transacciones,
        private readonly GeneradorId $generadorId,
        private readonly AsignadorCorrelativo $asignadorCorrelativo,
        private readonly RepositorioComprobante $repositorio,
        private readonly CalculadorTributos $calculadorTributos,
        private readonly ValidadorComprobante $validador,
        private readonly DespachadorProcesamiento $despachador,
        private readonly RepositorioCliente $repositorioCliente,
    ) {}

    abstract protected function tipo(): TipoComprobante;

    public function ejecutar(EmitirComprobanteInput $input): Comprobante
    {
        $comprobante = $this->transacciones->ejecutar(function () use ($input) {
            $numero = $this->asignadorCorrelativo->asignar(
                $input->empresaId,
                $this->tipo(),
                new Serie($input->serie),
            );

            $items = [];

            foreach ($input->items as $i => $itemInput) {
                $items[] = $this->calculadorTributos->calcularItem(
                    numeroOrden: $i + 1,
                    descripcion: $itemInput->descripcion,
                    unidadMedida: $itemInput->unidadMedida,
                    cantidad: $itemInput->cantidad,
                    valorUnitario: Dinero::desde($itemInput->valorUnitario),
                    tipoAfectacionIgv: $itemInput->tipoAfectacionIgv,
                    codigoProducto: $itemInput->codigoProducto,
                    descuento: $itemInput->descuento !== null ? Dinero::desde($itemInput->descuento) : null,
                );
            }

            $resultado = $this->calculadorTributos->calcularTotales($items);

            $comprobante = Comprobante::registrar(
                id: $this->generadorId->nuevo(),
                empresaId: $input->empresaId,
                tipo: $this->tipo(),
                numero: $numero,
                moneda: Moneda::from($input->moneda),
                receptorDocumento: new DocumentoIdentidad(
                    TipoDocumentoIdentidad::from($input->receptorTipoDocumento),
                    $input->receptorNumeroDocumento,
                ),
                receptorRazonSocial: $this->resolverRazonSocial($input),
                fechaEmision: new DateTimeImmutable('now'),
                referencia: $this->construirReferencia($input),
            );

            foreach ($items as $item) {
                $comprobante->agregarItem($item);
            }

            foreach ($resultado['tributos'] as $tributo) {
                $comprobante->agregarTributo($tributo);
            }

            $comprobante->definirTotales($resultado['totales']);

            $this->validador->validar($comprobante);

            $this->repositorio->guardar($comprobante);

            return $comprobante;
        });

        $this->despachador->despacharEnvio(
            $comprobante->empresaId(),
            $comprobante->id(),
            $input->requestId,
        );

        return $comprobante;
    }

    private function resolverRazonSocial(EmitirComprobanteInput $input): string
    {
        if ($input->receptorRazonSocial !== null && trim($input->receptorRazonSocial) !== '') {
            return trim($input->receptorRazonSocial);
        }

        $cliente = $this->repositorioCliente->buscarPorDocumento(
            $input->empresaId,
            TipoDocumentoCliente::from($input->receptorTipoDocumento),
            $input->receptorNumeroDocumento,
        );

        if ($cliente === null) {
            throw new ComprobanteInvalidoException(
                'No se indicó receptor_razon_social y no existe un cliente registrado con ese documento en esta empresa.',
            );
        }

        return $cliente->razonSocial();
    }

    private function construirReferencia(EmitirComprobanteInput $input): ?ReferenciaComprobante
    {
        if ($input->comprobanteReferenciaId === null) {
            return null;
        }

        return new ReferenciaComprobante(
            $input->comprobanteReferenciaId,
            $input->codigoMotivo ?? '',
            $input->descripcionMotivo ?? '',
        );
    }
}
