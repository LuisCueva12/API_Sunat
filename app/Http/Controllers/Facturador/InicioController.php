<?php

declare(strict_types=1);

namespace App\Http\Controllers\Facturador;

use App\Http\Controllers\Controller;
use App\Models\Comprobante;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class InicioController extends Controller
{
    public function __invoke(Request $request): View
    {
        /** @var Usuario $usuario */
        $usuario = $request->user();
        $consulta = Comprobante::query()->where('empresa_id', $usuario->empresa_id);

        return view('facturador.inicio', [
            'ventasHoy' => (clone $consulta)->whereDate('fecha_emision', today())->count(),
            'totalHoy' => (string) (clone $consulta)->whereDate('fecha_emision', today())->sum('total'),
            'recientes' => (clone $consulta)->latest('created_at')->limit(5)->get(),
        ]);
    }
}
