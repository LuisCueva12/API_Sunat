<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Api\RespuestaApi;
use App\Http\Controllers\Api\V1\Concerns\InteractuaConComprobantes;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\EmitirBoletaRequest;
use Illuminate\Http\JsonResponse;
use Modules\Facturacion\Application\CasosDeUso\EmitirBoleta;

final class BoletaController extends Controller
{
    use InteractuaConComprobantes;

    public function store(EmitirBoletaRequest $request, EmitirBoleta $emitirBoleta): JsonResponse
    {
        $comprobante = $emitirBoleta->ejecutar($this->construirInput($request->validated(), $request));

        return RespuestaApi::exito($this->transformarComprobante($comprobante), 202);
    }
}
