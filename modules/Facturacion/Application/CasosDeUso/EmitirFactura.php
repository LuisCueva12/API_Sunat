<?php

declare(strict_types=1);

namespace Modules\Facturacion\Application\CasosDeUso;

use DateTimeImmutable;
use Modules\Facturacion\Application\DTO\EmitirComprobanteInput;
use Modules\Facturacion\Domain\Comprobante\Comprobante;
use Modules\Facturacion\Domain\Comprobante\TipoComprobante;
use Modules\Facturacion\Domain\Puertos\AsignadorCorrelativo;
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

final class EmitirFactura
{
    public function __construct(
        private readonly GestorTransacciones $transacciones,
        private readonly GeneradorId $generadorId,
        private readonly AsignadorCorrelativo $asignadorCorrelativo,
        private readonly RepositorioComprobante $repositorio,
        private readonly CalculadorTributos $calculadorTributos,
        private readonly ValidadorComprobante $validador,
    ) {}

    public function ejecutar(EmitirComprobanteInput $input): Comprobante
    {
        return $this->transacciones->ejecutar(function () use ($input) {
            $numero = $this->asignadorCorrelativo->asignar(
                $input->empresaId,
                TipoComprobante::Factura,
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
                tipo: TipoComprobante::Factura,
                numero: $numero,
                moneda: Moneda::from($input->moneda),
                receptorDocumento: new DocumentoIdentidad(
                    TipoDocumentoIdentidad::from($input->receptorTipoDocumento),
                    $input->receptorNumeroDocumento,
                ),
                receptorRazonSocial: $input->receptorRazonSocial,
                fechaEmision: new DateTimeImmutable('now'),
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
    }
}
