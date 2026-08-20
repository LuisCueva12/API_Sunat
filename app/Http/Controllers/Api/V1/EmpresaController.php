<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Api\RespuestaApi;
use App\Http\Controllers\Controller;
use App\Models\Empresa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class EmpresaController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $empresa = Empresa::query()->findOrFail($request->attributes->get('empresa_id'));

        return RespuestaApi::exito([
            'id' => $empresa->id,
            'ruc' => $empresa->ruc,
            'razon_social' => $empresa->razon_social,
            'nombre_comercial' => $empresa->nombre_comercial,
            'estado' => $empresa->estado,
        ]);
    }
}
