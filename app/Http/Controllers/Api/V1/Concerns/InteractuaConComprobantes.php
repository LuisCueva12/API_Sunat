<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Concerns;

use Illuminate\Http\Request;
use Modules\Facturacion\Application\DTO\EmitirComprobanteInput;
use Modules\Facturacion\Application\DTO\ItemInput;
use Modules\Facturacion\Domain\Comprobante\Comprobante;

trait InteractuaConComprobantes
{

    protected function construirInput(array $validado, Request $request): EmitirComprobanteInput
    {
        return new EmitirComprobanteInput(
            empresaId: (string) $request->attributes->get('empresa_id'),
            serie: $validado['serie'],
            receptorTipoDocumento: $validado['receptor_tipo_documento'],
            receptorNumeroDocumento: $validado['receptor_numero_documento'] ?? '',
            receptorRazonSocial: $validado['receptor_razon_social'],
            items: array_map(
                fn (array $item) => new ItemInput(
                    descripcion: $item['descripcion'],
                    unidadMedida: $item['unidad_medida'],
                    cantidad: (float) $item['cantidad'],
                    valorUnitario: (string) $item['valor_unitario'],
                    tipoAfectacionIgv: $item['tipo_afectacion_igv'],
                    codigoProducto: $item['codigo_producto'] ?? null,
                    descuento: isset($item['descuento']) ? (string) $item['descuento'] : null,
                ),
                $validado['items'],
            ),
            moneda: $validado['moneda'] ?? 'PEN',
            comprobanteReferenciaId: $validado['comprobante_referencia_id'] ?? null,
            codigoMotivo: $validado['codigo_motivo'] ?? null,
            descripcionMotivo: $validado['descripcion_motivo'] ?? null,
            requestId: $request->attributes->getString('request_id'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function transformarComprobante(Comprobante $comprobante): array
    {
        return [
            'id' => $comprobante->id(),
            'tipo' => strtolower($comprobante->tipo()->value),
            'serie' => $comprobante->numero()->serie()->valor(),
            'numero' => $comprobante->numero()->correlativo(),
            'estado' => $comprobante->estado()->value,
        ];
    }
}
