<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Api\RespuestaApi;
use App\Http\Controllers\Api\V1\Concerns\InteractuaConComprobantes;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\EmitirNotaCreditoRequest;
use Illuminate\Http\JsonResponse;
use Modules\Facturacion\Application\CasosDeUso\EmitirNotaCredito;

final class NotaCreditoController extends Controller
{
    use InteractuaConComprobantes;

    public function store(EmitirNotaCreditoRequest $request, EmitirNotaCredito $emitirNotaCredito): JsonResponse
    {
        $comprobante = $emitirNotaCredito->ejecutar($this->construirInput($request->validated(), $request));

        return RespuestaApi::exito($this->transformarComprobante($comprobante), 202);
    }
}
