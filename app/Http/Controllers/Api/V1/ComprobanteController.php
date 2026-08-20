<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Api\RespuestaApi;
use App\Http\Controllers\Controller;
use App\Models\Comprobante as ComprobanteEloquent;
use App\Services\Comprobantes\GeneradorRepresentacionImpresa;
use App\Services\Comprobantes\ObtenedorXmlFirmado;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Facturacion\Application\CasosDeUso\ReintentarComprobante;
use Modules\Facturacion\Domain\Puertos\AlmacenPrivado;
use Symfony\Component\HttpFoundation\Response;

final class ComprobanteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'tipo' => ['sometimes', 'string'],
            'estado' => ['sometimes', 'string'],
            'serie' => ['sometimes', 'string'],
            'fecha_desde' => ['sometimes', 'date'],
            'fecha_hasta' => ['sometimes', 'date'],
            'por_pagina' => ['sometimes', 'integer'],
        ]);

        $query = ComprobanteEloquent::query()
            ->where('empresa_id', $request->attributes->get('empresa_id'));

        if ($request->filled('tipo')) {
            $query->where('tipo', strtoupper((string) $request->string('tipo')));
        }

        if ($request->filled('estado')) {
            $query->where('estado', strtoupper((string) $request->string('estado')));
        }

        if ($request->filled('serie')) {
            $query->where('serie', (string) $request->string('serie'));
        }

        if ($request->filled('fecha_desde')) {
            $query->whereDate('fecha_emision', '>=', $request->date('fecha_desde'));
        }

        if ($request->filled('fecha_hasta')) {
            $query->whereDate('fecha_emision', '<=', $request->date('fecha_hasta'));
        }

        $comprobantes = $query->orderByDesc('created_at')
            ->paginate(perPage: min(max($request->integer('por_pagina', 20), 1), 100));

        return RespuestaApi::exito(
            $comprobantes->map($this->transformarFila(...))->all(),
            200,
            [
                'pagina' => $comprobantes->currentPage(),
                'total' => $comprobantes->total(),
                'por_pagina' => $comprobantes->perPage(),
            ],
        );
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $comprobante = $this->buscarOFallar($request, $id);
        $comprobante->load(['items', 'tributos', 'referencia']);

        return RespuestaApi::exito($this->transformarDetalle($comprobante));
    }

    public function estado(Request $request, string $id): JsonResponse
    {
        $comprobante = $this->buscarOFallar($request, $id);

        return RespuestaApi::exito([
            'id' => $comprobante->id,
            'estado' => $comprobante->estado,
            'actualizado_en' => $comprobante->updated_at->toIso8601String(),
        ]);
    }

    public function eventos(Request $request, string $id): JsonResponse
    {
        $comprobante = $this->buscarOFallar($request, $id);

        return RespuestaApi::exito($comprobante->eventos()->get()->map(static function ($evento): array {
            $datos = $evento->datos;

            if ($evento->tipo_evento === 'ERROR' && is_array($datos)) {
                unset($datos['mensaje']);
            }

            return [
                'id' => $evento->id,
                'tipo' => $evento->tipo_evento,
                'actor' => $evento->actor,
                'request_id' => $evento->request_id,
                'datos' => $datos,
                'creado_en' => $evento->created_at->toIso8601String(),
            ];
        })->all());
    }

    public function xml(Request $request, string $id, ObtenedorXmlFirmado $obtenedor): Response
    {
        $comprobante = $this->buscarOFallar($request, $id);
        $ruta = $this->rutaBase($comprobante).'/comprobante.xml';

        try {
            $contenido = $obtenedor->obtener($comprobante, $ruta);
        } catch (\RuntimeException) {
            abort(404, 'El XML firmado no está disponible.');
        }

        return $this->respuestaDescarga($contenido, $this->nombreBase($comprobante).'.xml', 'application/xml; charset=UTF-8');
    }

    public function cdr(Request $request, string $id, AlmacenPrivado $almacen): Response
    {
        $comprobante = $this->buscarOFallar($request, $id);

        return $this->descargarArchivo(
            $almacen,
            $this->rutaBase($comprobante).'/cdr.zip',
            'R-'.$this->nombreBase($comprobante).'.zip',
            'application/zip',
        );
    }

    public function pdf(Request $request, string $id, GeneradorRepresentacionImpresa $generador): Response
    {
        $comprobante = $this->buscarOFallar($request, $id);

        if (! in_array($comprobante->estado, ['ACEPTADO', 'ACEPTADO_CON_OBSERVACIONES'], true)) {
            return RespuestaApi::error(
                'PDF_NO_DISPONIBLE',
                'El PDF estará disponible cuando SUNAT acepte el comprobante.',
                409,
            );
        }

        $representacion = $generador->generar($comprobante);

        return new Response($representacion->contenido, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$representacion->nombreArchivo.'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function reintentar(Request $request, string $id, ReintentarComprobante $reintentar): JsonResponse
    {
        $comprobante = $reintentar->ejecutar(
            (string) $request->attributes->get('empresa_id'),
            $id,
            $request->attributes->getString('request_id'),
        );

        return RespuestaApi::exito([
            'id' => $comprobante->id(),
            'estado' => $comprobante->estado()->value,
            'reintento_programado' => true,
        ], 202);
    }

    private function buscarOFallar(Request $request, string $id): ComprobanteEloquent
    {
        $comprobante = ComprobanteEloquent::query()
            ->where('empresa_id', $request->attributes->get('empresa_id'))
            ->find($id);

        if ($comprobante === null) {
            abort(404, 'El comprobante solicitado no existe.');
        }

        return $comprobante;
    }

    /**
     * @return array<string, mixed>
     */
    private function transformarFila(ComprobanteEloquent $fila): array
    {
        return [
            'id' => $fila->id,
            'tipo' => strtolower($fila->tipo),
            'serie' => $fila->serie,
            'numero' => (int) $fila->correlativo,
            'estado' => $fila->estado,
            'receptor' => [
                'tipo_documento' => $fila->receptor_tipo_documento,
                'numero_documento' => $fila->receptor_numero_documento,
                'razon_social' => $fila->receptor_razon_social,
            ],
            'moneda' => $fila->moneda,
            'total' => $fila->total,
            'fecha_emision' => $fila->fecha_emision->toDateString(),
            'creado_en' => $fila->created_at->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function transformarDetalle(ComprobanteEloquent $fila): array
    {
        return array_merge($this->transformarFila($fila), [
            'fecha_vencimiento' => $fila->fecha_vencimiento?->toDateString(),
            'forma_pago' => $fila->forma_pago,
            'tipo_cambio' => $fila->tipo_cambio,
            'receptor' => [
                'tipo_documento' => $fila->receptor_tipo_documento,
                'numero_documento' => $fila->receptor_numero_documento,
                'razon_social' => $fila->receptor_razon_social,
                'direccion' => $fila->receptor_direccion,
                'email' => $fila->receptor_email,
            ],
            'totales' => [
                'op_gravada' => $fila->op_gravada,
                'op_exonerada' => $fila->op_exonerada,
                'op_inafecta' => $fila->op_inafecta,
                'op_gratuita' => $fila->op_gratuita,
                'igv' => $fila->total_igv,
                'descuentos' => $fila->total_descuentos,
                'total' => $fila->total,
            ],
            'referencia' => $fila->referencia === null ? null : [
                'id' => $fila->referencia->id,
                'tipo' => strtolower($fila->referencia->tipo),
                'serie' => $fila->referencia->serie,
                'numero' => (int) $fila->referencia->correlativo,
                'codigo_motivo' => $fila->tipo_nota,
                'motivo' => $fila->motivo_nota,
            ],
            'items' => $fila->items->map(static fn ($item): array => [
                'numero_orden' => $item->numero_orden,
                'codigo_producto' => $item->codigo_producto,
                'descripcion' => $item->descripcion,
                'unidad_medida' => $item->unidad_medida,
                'cantidad' => $item->cantidad,
                'valor_unitario' => $item->valor_unitario,
                'precio_unitario' => $item->precio_unitario,
                'tipo_afectacion_igv' => $item->tipo_afectacion_igv,
                'igv' => $item->monto_igv,
                'valor_venta' => $item->monto_valor_venta,
                'descuento' => $item->descuento,
            ])->all(),
            'tributos' => $fila->tributos->map(static fn ($tributo): array => [
                'tipo' => $tributo->tipo_tributo,
                'codigo' => $tributo->codigo,
                'base_imponible' => $tributo->base_imponible,
                'monto' => $tributo->monto,
            ])->all(),
            'procesamiento' => [
                'intentos' => $fila->intentos_envio,
                'xml_sha256' => $fila->xml_sha256,
                'cdr_sha256' => $fila->cdr_sha256,
                'actualizado_en' => $fila->updated_at->toIso8601String(),
            ],
        ]);
    }

    private function descargarArchivo(
        AlmacenPrivado $almacen,
        string $ruta,
        string $nombre,
        string $contentType,
    ): Response {
        if (! $almacen->existe($ruta)) {
            abort(404, 'El archivo solicitado no está disponible.');
        }

        return $this->respuestaDescarga($almacen->leer($ruta), $nombre, $contentType);
    }

    private function respuestaDescarga(string $contenido, string $nombre, string $contentType): Response
    {
        return new Response($contenido, 200, [
            'Content-Type' => $contentType,
            'Content-Disposition' => 'attachment; filename="'.$nombre.'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function rutaBase(ComprobanteEloquent $comprobante): string
    {
        return sprintf(
            'empresas/%s/comprobantes/%s/%s/%s',
            $comprobante->empresa_id,
            $comprobante->fecha_emision->format('Y'),
            $comprobante->fecha_emision->format('m'),
            $comprobante->id,
        );
    }

    private function nombreBase(ComprobanteEloquent $comprobante): string
    {
        $ruc = (string) data_get($comprobante->snapshot_emisor, 'ruc', 'comprobante');
        $tipo = match ($comprobante->tipo) {
            'FACTURA' => '01',
            'BOLETA' => '03',
            'NOTA_CREDITO' => '07',
            'NOTA_DEBITO' => '08',
            default => 'documento',
        };

        return sprintf('%s-%s-%s-%d', $ruc, $tipo, $comprobante->serie, $comprobante->correlativo);
    }
}
