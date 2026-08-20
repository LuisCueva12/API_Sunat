<?php

declare(strict_types=1);

namespace App\Http\Controllers\Facturador;

use App\Http\Controllers\Controller;
use App\Http\Requests\Facturador\GuardarProductoServicioRequest;
use App\Models\ProductoServicio;
use App\Models\Usuario;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class ProductoServicioController extends Controller
{
    public function index(Request $request): View
    {
        /** @var Usuario $usuario */
        $usuario = $request->user();
        $busqueda = trim($request->string('q')->toString());
        $consulta = ProductoServicio::query()->where('empresa_id', $usuario->empresa_id);

        if ($busqueda !== '') {
            $consulta->where(fn ($query) => $query
                ->where('nombre', 'ilike', "%{$busqueda}%")
                ->orWhere('codigo', 'ilike', "%{$busqueda}%"));
        }

        return view('facturador.productos.index', [
            'productos' => $consulta->orderByDesc('activo')->orderBy('nombre')->paginate(15)->withQueryString(),
        ]);
    }

    public function store(GuardarProductoServicioRequest $request): RedirectResponse
    {
        /** @var Usuario $usuario */
        $usuario = $request->user();
        $datos = $request->validated();

        ProductoServicio::query()->create([
            'empresa_id' => $usuario->empresa_id,
            'codigo' => filled($datos['codigo'] ?? null) ? $datos['codigo'] : null,
            'nombre' => $datos['nombre'],
            'tipo' => $datos['tipo'],
            'unidad_medida' => $datos['unidad_medida'],
            'valor_unitario' => $datos['valor_unitario'],
            'activo' => true,
        ]);

        return back()->with('success', 'Producto o servicio guardado. Ya puedes usarlo en una venta.');
    }
}
