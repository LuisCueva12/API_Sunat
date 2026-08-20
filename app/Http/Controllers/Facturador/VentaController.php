<?php

declare(strict_types=1);

namespace App\Http\Controllers\Facturador;

use App\Http\Controllers\Controller;
use App\Http\Requests\Facturador\EmitirVentaRequest;
use App\Models\Cliente;
use App\Models\Comprobante as ComprobanteEloquent;
use App\Models\ProductoServicio;
use App\Models\Serie;
use App\Models\Usuario;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use InvalidArgumentException;
use Modules\Facturacion\Application\CasosDeUso\EmitirBoleta;
use Modules\Facturacion\Application\CasosDeUso\EmitirFactura;
use Modules\Facturacion\Application\DTO\EmitirComprobanteInput;
use Modules\Facturacion\Application\DTO\ItemInput;
use Throwable;

final class VentaController extends Controller
{
    public function create(Request $request): View
    {
        /** @var Usuario $usuario */
        $usuario = $request->user();
        $series = Serie::query()
            ->where('empresa_id', $usuario->empresa_id)
            ->whereIn('tipo_comprobante', ['BOLETA', 'FACTURA'])
            ->where('activa', true)
            ->orderBy('serie')
            ->get()
            ->groupBy('tipo_comprobante')
            ->map(fn ($grupo) => $grupo->pluck('serie')->values()->all());

        return view('facturador.ventas.create', ['series' => $series]);
    }

    public function store(EmitirVentaRequest $request): RedirectResponse
    {
        $datos = $request->validated();
        /** @var Usuario $usuario */
        $usuario = $request->user();

        try {
            $casoDeUso = $datos['tipo'] === 'FACTURA' ? app(EmitirFactura::class) : app(EmitirBoleta::class);
            $comprobante = $casoDeUso->ejecutar(new EmitirComprobanteInput(
                empresaId: (string) $usuario->empresa_id,
                serie: (string) $datos['serie'],
                receptorTipoDocumento: $this->tipoDocumentoDominio((string) $datos['receptor_tipo_documento']),
                receptorNumeroDocumento: (string) ($datos['receptor_numero_documento'] ?? ''),
                receptorRazonSocial: (string) $datos['receptor_razon_social'],
                items: array_map(fn (array $item): ItemInput => new ItemInput(
                    descripcion: (string) $item['descripcion'],
                    unidadMedida: (string) $item['unidad_medida'],
                    cantidad: (float) $item['cantidad'],
                    valorUnitario: (string) $item['valor_unitario'],
                    tipoAfectacionIgv: '10',
                    codigoProducto: filled($item['codigo_producto'] ?? null) ? (string) $item['codigo_producto'] : null,
                    descuento: filled($item['descuento'] ?? null) ? (string) $item['descuento'] : null,
                ), $datos['items']),
                moneda: 'PEN',
                requestId: $request->attributes->getString('request_id') ?: null,
            ));
        } catch (InvalidArgumentException|DomainException $e) {
            return back()->withInput()->with('error', $this->mensajeAmigable($e));
        } catch (Throwable $e) {
            Log::error('No se pudo emitir desde el facturador', [
                'empresa_id' => $usuario->empresa_id,
                'excepcion' => $e::class,
                'mensaje' => $e->getMessage(),
            ]);

            return back()->withInput()->with('error', 'No pudimos emitir en este momento. Revisa tu conexión e intenta nuevamente.');
        }

        return redirect()->route('facturador.ventas.confirmacion', $comprobante->id());
    }

    public function confirmacion(Request $request, string $id): View
    {
        return view('facturador.ventas.confirmacion', [
            'comprobante' => $this->comprobanteDelTenant($request, $id),
        ]);
    }

    public function estado(Request $request, string $id): JsonResponse
    {
        $comprobante = $this->comprobanteDelTenant($request, $id);

        return response()->json([
            'estado' => $comprobante->estado,
            'etiqueta' => $this->etiquetaEstado($comprobante->estado),
            'terminal' => in_array($comprobante->estado, ['ACEPTADO', 'ACEPTADO_CON_OBSERVACIONES', 'RECHAZADO', 'ERROR'], true),
        ]);
    }

    public function buscarClientes(Request $request): JsonResponse
    {
        /** @var Usuario $usuario */
        $usuario = $request->user();
        $busqueda = trim($request->string('q')->toString());
        $tipo = $request->string('tipo')->toString();

        $consulta = Cliente::query()->where('empresa_id', $usuario->empresa_id);

        if ($tipo === 'FACTURA') {
            $consulta->where('tipo_documento', '6');
        } else {
            $consulta->where('tipo_documento', '!=', '6');
        }

        if ($busqueda !== '') {
            $consulta->where(fn ($query) => $query
                ->where('numero_documento', 'ilike', "%{$busqueda}%")
                ->orWhere('razon_social', 'ilike', "%{$busqueda}%"));
        }

        return response()->json($consulta->orderBy('razon_social')->limit(8)->get()->map(fn (Cliente $cliente) => [
            'id' => $cliente->id,
            'tipo_documento' => $this->tipoDocumentoFormulario($cliente->tipo_documento),
            'numero_documento' => $cliente->numero_documento,
            'nombre' => $cliente->razon_social,
        ]));
    }

    public function buscarProductos(Request $request): JsonResponse
    {
        /** @var Usuario $usuario */
        $usuario = $request->user();
        $busqueda = trim($request->string('q')->toString());

        $consulta = ProductoServicio::query()
            ->where('empresa_id', $usuario->empresa_id)
            ->where('activo', true);

        if ($busqueda !== '') {
            $consulta->where(fn ($query) => $query
                ->where('nombre', 'ilike', "%{$busqueda}%")
                ->orWhere('codigo', 'ilike', "%{$busqueda}%"));
        }

        return response()->json($consulta->orderBy('nombre')->limit(8)->get()->map(fn (ProductoServicio $producto) => [
            'id' => $producto->id,
            'codigo' => $producto->codigo,
            'nombre' => $producto->nombre,
            'unidad_medida' => $producto->unidad_medida,
            'valor_unitario' => $producto->valor_unitario,
        ]));
    }

    private function comprobanteDelTenant(Request $request, string $id): ComprobanteEloquent
    {
        /** @var Usuario $usuario */
        $usuario = $request->user();

        return ComprobanteEloquent::query()
            ->where('empresa_id', $usuario->empresa_id)
            ->findOrFail($id);
    }

    private function tipoDocumentoDominio(string $tipo): string
    {
        return match ($tipo) {
            'SIN_DOCUMENTO' => '0',
            'DNI' => '1',
            'CARNET_EXTRANJERIA' => '4',
            'RUC' => '6',
            'PASAPORTE' => '7',
            default => throw new InvalidArgumentException('Selecciona un documento válido.'),
        };
    }

    private function tipoDocumentoFormulario(string $tipo): string
    {
        return match ($tipo) {
            '0' => 'SIN_DOCUMENTO', '1' => 'DNI', '4' => 'CARNET_EXTRANJERIA', '6' => 'RUC', '7' => 'PASAPORTE',
            default => 'SIN_DOCUMENTO',
        };
    }

    private function mensajeAmigable(Throwable $e): string
    {
        $mensaje = $e->getMessage();

        if (str_contains(mb_strtolower($mensaje), 'configur')) {
            return 'Tu empresa todavía no está lista para emitir. Contacta al administrador.';
        }

        if ($e instanceof InvalidArgumentException) {
            return $mensaje;
        }

        return 'No pudimos emitir con esos datos. Revísalos e intenta nuevamente.';
    }

    private function etiquetaEstado(string $estado): string
    {
        return match ($estado) {
            'REGISTRADO' => 'Preparando envío',
            'PROCESANDO' => 'Enviando a SUNAT',
            'ACEPTADO' => 'Aceptado por SUNAT',
            'ACEPTADO_CON_OBSERVACIONES' => 'Aceptado con observaciones',
            'RECHAZADO' => 'SUNAT no aceptó el comprobante',
            'ERROR' => 'No se pudo completar el envío',
            default => 'Procesando',
        };
    }
}
