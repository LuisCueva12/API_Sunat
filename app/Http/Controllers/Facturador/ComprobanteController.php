<?php

declare(strict_types=1);

namespace App\Http\Controllers\Facturador;

use App\Http\Controllers\Controller;
use App\Models\Comprobante;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class ComprobanteController extends Controller
{
    public function index(Request $request): View
    {
        /** @var Usuario $usuario */
        $usuario = $request->user();
        $busqueda = trim($request->string('q')->toString());
        $estado = $request->string('estado')->toString();

        $consulta = Comprobante::query()->where('empresa_id', $usuario->empresa_id);

        if ($busqueda !== '') {
            $consulta->where(fn ($query) => $query
                ->where('receptor_razon_social', 'ilike', "%{$busqueda}%")
                ->orWhere('receptor_numero_documento', 'ilike', "%{$busqueda}%")
                ->orWhereRaw("serie || '-' || correlativo::text ILIKE ?", ["%{$busqueda}%"]));
        }

        if (in_array($estado, ['REGISTRADO', 'PROCESANDO', 'ACEPTADO', 'ACEPTADO_CON_OBSERVACIONES', 'RECHAZADO', 'ERROR'], true)) {
            $consulta->where('estado', $estado);
        }

        return view('facturador.comprobantes.index', [
            'comprobantes' => $consulta->latest('created_at')->paginate(15)->withQueryString(),
        ]);
    }

    public function show(Request $request, string $id): View
    {
        /** @var Usuario $usuario */
        $usuario = $request->user();
        $comprobante = Comprobante::query()
            ->with('items')
            ->where('empresa_id', $usuario->empresa_id)
            ->findOrFail($id);

        return view('facturador.comprobantes.show', compact('comprobante'));
    }
}
