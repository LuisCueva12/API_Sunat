<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Api\RespuestaApi;
use App\Http\Controllers\Controller;
use App\Models\Comprobante as ComprobanteEloquent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Facturacion\Application\CasosDeUso\ReintentarComprobante;

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
        return RespuestaApi::exito($this->transformarFila($this->buscarOFallar($request, $id)));
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
}
