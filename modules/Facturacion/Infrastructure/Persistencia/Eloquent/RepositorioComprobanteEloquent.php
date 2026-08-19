<?php

declare(strict_types=1);

namespace Modules\Facturacion\Infrastructure\Persistencia\Eloquent;

use App\Models\Comprobante as ComprobanteEloquent;
use App\Models\ComprobanteItem as ComprobanteItemEloquent;
use App\Models\ComprobanteTributo as ComprobanteTributoEloquent;
use App\Models\Empresa as EmpresaEloquent;
use DateTimeImmutable;
use Modules\Facturacion\Domain\Comprobante\Comprobante;
use Modules\Facturacion\Domain\Comprobante\EstadoComprobante;
use Modules\Facturacion\Domain\Comprobante\ItemComprobante;
use Modules\Facturacion\Domain\Comprobante\ReferenciaComprobante;
use Modules\Facturacion\Domain\Comprobante\TipoComprobante;
use Modules\Facturacion\Domain\Comprobante\TotalesComprobante;
use Modules\Facturacion\Domain\Comprobante\Tributo;
use Modules\Facturacion\Domain\Excepciones\ComprobanteInvalidoException;
use Modules\Facturacion\Domain\Puertos\RepositorioComprobante;
use Modules\Facturacion\Domain\ValueObjects\Dinero;
use Modules\Facturacion\Domain\ValueObjects\DocumentoIdentidad;
use Modules\Facturacion\Domain\ValueObjects\Moneda;
use Modules\Facturacion\Domain\ValueObjects\NumeroComprobante;
use Modules\Facturacion\Domain\ValueObjects\Serie;
use Modules\Facturacion\Domain\ValueObjects\TipoDocumentoIdentidad;

final class RepositorioComprobanteEloquent implements RepositorioComprobante
{
    public function guardar(Comprobante $comprobante): void
    {
        $empresa = EmpresaEloquent::query()->find($comprobante->empresaId());

        if ($empresa === null) {
            throw new ComprobanteInvalidoException("No existe la empresa {$comprobante->empresaId()}.");
        }

        $totales = $comprobante->totales();
        $referencia = $comprobante->referencia();

        $fila = ComprobanteEloquent::query()->create([
            'id' => $comprobante->id(),
            'empresa_id' => $comprobante->empresaId(),
            'tipo' => $comprobante->tipo()->value,
            'serie' => $comprobante->numero()->serie()->valor(),
            'correlativo' => $comprobante->numero()->correlativo(),
            'estado' => $comprobante->estado()->value,
            'moneda' => $comprobante->moneda()->value,
            'receptor_tipo_documento' => $comprobante->receptorDocumento()->tipo()->value,
            'receptor_numero_documento' => $comprobante->receptorDocumento()->numero(),
            'receptor_razon_social' => $comprobante->receptorRazonSocial(),
            'fecha_emision' => $comprobante->fechaEmision()->format('Y-m-d'),
            'op_gravada' => $totales?->opGravada->comoString() ?? '0.00',
            'op_exonerada' => $totales?->opExonerada->comoString() ?? '0.00',
            'op_inafecta' => $totales?->opInafecta->comoString() ?? '0.00',
            'op_gratuita' => $totales?->opGratuita->comoString() ?? '0.00',
            'total_igv' => $totales?->totalIgv->comoString() ?? '0.00',
            'total_descuentos' => $totales?->totalDescuentos->comoString() ?? '0.00',
            'total' => $totales?->total->comoString() ?? '0.00',
            'comprobante_referencia_id' => $referencia?->comprobanteId(),
            'tipo_nota' => $referencia?->codigoMotivo(),
            'motivo_nota' => $referencia?->descripcionMotivo(),
            'snapshot_emisor' => [
                'ruc' => $empresa->ruc,
                'razon_social' => $empresa->razon_social,
                'nombre_comercial' => $empresa->nombre_comercial,
            ],
        ]);

        foreach ($comprobante->items() as $item) {
            ComprobanteItemEloquent::query()->create([
                'comprobante_id' => $fila->id,
                'numero_orden' => $item->numeroOrden(),
                'codigo_producto' => $item->codigoProducto(),
                'descripcion' => $item->descripcion(),
                'unidad_medida' => $item->unidadMedida(),
                'cantidad' => $item->cantidad(),
                'valor_unitario' => $item->valorUnitario()->comoString(),
                'precio_unitario' => $item->precioUnitario()->comoString(),
                'tipo_afectacion_igv' => $item->tipoAfectacionIgv(),
                'monto_igv' => $item->montoIgv()->comoString(),
                'monto_valor_venta' => $item->montoValorVenta()->comoString(),
                'descuento' => $item->descuento()->comoString(),
            ]);
        }

        foreach ($comprobante->tributos() as $tributo) {
            ComprobanteTributoEloquent::query()->create([
                'comprobante_id' => $fila->id,
                'tipo_tributo' => $tributo->tipoTributo(),
                'codigo' => $tributo->codigo(),
                'base_imponible' => $tributo->baseImponible()->comoString(),
                'monto' => $tributo->monto()->comoString(),
            ]);
        }
    }

    public function actualizarEstado(
        Comprobante $comprobante,
        ?string $xmlSha256 = null,
        ?string $cdrSha256 = null,
    ): void {
        $datos = [
            'estado' => $comprobante->estado()->value,
            'intentos_envio' => $comprobante->intentosEnvio(),
            'ultimo_error' => $comprobante->ultimoError(),
        ];

        if ($xmlSha256 !== null) {
            $datos['xml_sha256'] = $xmlSha256;
        }

        if ($cdrSha256 !== null) {
            $datos['cdr_sha256'] = $cdrSha256;
        }

        ComprobanteEloquent::query()
            ->where('id', $comprobante->id())
            ->where('empresa_id', $comprobante->empresaId())
            ->update($datos);
    }

    public function buscarPorId(string $empresaId, string $id): ?Comprobante
    {
        $fila = ComprobanteEloquent::query()
            ->with(['items', 'tributos'])
            ->where('empresa_id', $empresaId)
            ->find($id);

        if ($fila === null) {
            return null;
        }

        $comprobante = Comprobante::reconstituir(
            id: $fila->id,
            empresaId: $fila->empresa_id,
            tipo: TipoComprobante::from($fila->tipo),
            numero: new NumeroComprobante(new Serie($fila->serie), (int) $fila->correlativo),
            estado: EstadoComprobante::from($fila->estado),
            moneda: Moneda::from($fila->moneda),
            receptorDocumento: new DocumentoIdentidad(
                TipoDocumentoIdentidad::from($fila->receptor_tipo_documento),
                $fila->receptor_numero_documento,
            ),
            receptorRazonSocial: $fila->receptor_razon_social,
            fechaEmision: new DateTimeImmutable($fila->fecha_emision->format('Y-m-d')),
            referencia: $fila->comprobante_referencia_id !== null
                ? new ReferenciaComprobante(
                    $fila->comprobante_referencia_id,
                    $fila->tipo_nota ?? '',
                    $fila->motivo_nota ?? '',
                )
                : null,
            totales: new TotalesComprobante(
                opGravada: Dinero::desde((string) $fila->op_gravada),
                opExonerada: Dinero::desde((string) $fila->op_exonerada),
                opInafecta: Dinero::desde((string) $fila->op_inafecta),
                opGratuita: Dinero::desde((string) $fila->op_gratuita),
                totalIgv: Dinero::desde((string) $fila->total_igv),
                totalDescuentos: Dinero::desde((string) $fila->total_descuentos),
                total: Dinero::desde((string) $fila->total),
            ),
            intentosEnvio: $fila->intentos_envio,
            ultimoError: $fila->ultimo_error,
        );

        foreach ($fila->items as $itemFila) {
            $comprobante->agregarItem(new ItemComprobante(
                numeroOrden: $itemFila->numero_orden,
                descripcion: $itemFila->descripcion,
                unidadMedida: $itemFila->unidad_medida,
                cantidad: (float) $itemFila->cantidad,
                valorUnitario: Dinero::desde((string) $itemFila->valor_unitario),
                precioUnitario: Dinero::desde((string) $itemFila->precio_unitario),
                tipoAfectacionIgv: $itemFila->tipo_afectacion_igv,
                montoIgv: Dinero::desde((string) $itemFila->monto_igv),
                montoValorVenta: Dinero::desde((string) $itemFila->monto_valor_venta),
                descuento: Dinero::desde((string) $itemFila->descuento),
                codigoProducto: $itemFila->codigo_producto,
            ));
        }

        foreach ($fila->tributos as $tributoFila) {
            $comprobante->agregarTributo(new Tributo(
                tipoTributo: $tributoFila->tipo_tributo,
                codigo: $tributoFila->codigo,
                baseImponible: Dinero::desde((string) $tributoFila->base_imponible),
                monto: Dinero::desde((string) $tributoFila->monto),
            ));
        }

        return $comprobante;
    }
}
