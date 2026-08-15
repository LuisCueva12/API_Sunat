<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Api\RespuestaApi;
use App\Http\Controllers\Api\V1\Concerns\InteractuaConComprobantes;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\EmitirNotaDebitoRequest;
use Illuminate\Http\JsonResponse;
use Modules\Facturacion\Application\CasosDeUso\EmitirNotaDebito;

final class NotaDebitoController extends Controller
{
    use InteractuaConComprobantes;

    public function store(EmitirNotaDebitoRequest $request, EmitirNotaDebito $emitirNotaDebito): JsonResponse
    {
        $comprobante = $emitirNotaDebito->ejecutar($this->construirInput($request->validated(), $request));

        return RespuestaApi::exito($this->transformarComprobante($comprobante), 202);
    }
}
