<?php

declare(strict_types=1);

namespace App\Http\Controllers\Facturador;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class CuentaController extends Controller
{
    public function __invoke(Request $request): View
    {
        /** @var Usuario $usuario */
        $usuario = $request->user();

        return view('facturador.cuenta.show', [
            'empresa' => Empresa::query()->findOrFail($usuario->empresa_id),
        ]);
    }
}
