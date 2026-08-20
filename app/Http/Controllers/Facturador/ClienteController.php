<?php

declare(strict_types=1);

namespace App\Http\Controllers\Facturador;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class ClienteController extends Controller
{
    public function index(Request $request): View
    {
        /** @var Usuario $usuario */
        $usuario = $request->user();
        $busqueda = trim($request->string('q')->toString());
        $consulta = Cliente::query()->where('empresa_id', $usuario->empresa_id);

        if ($busqueda !== '') {
            $consulta->where(fn ($query) => $query
                ->where('razon_social', 'ilike', "%{$busqueda}%")
                ->orWhere('numero_documento', 'ilike', "%{$busqueda}%"));
        }

        return view('facturador.clientes.index', [
            'clientes' => $consulta->orderBy('razon_social')->paginate(15)->withQueryString(),
        ]);
    }
}
