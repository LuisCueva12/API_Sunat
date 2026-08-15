<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Api\RespuestaApi;
use App\Http\Controllers\Api\V1\Concerns\InteractuaConComprobantes;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\EmitirFacturaRequest;
use Illuminate\Http\JsonResponse;
use Modules\Facturacion\Application\CasosDeUso\EmitirFactura;

final class FacturaController extends Controller
{
    use InteractuaConComprobantes;

    public function store(EmitirFacturaRequest $request, EmitirFactura $emitirFactura): JsonResponse
    {
        $comprobante = $emitirFactura->ejecutar($this->construirInput($request->validated(), $request));

        return RespuestaApi::exito($this->transformarComprobante($comprobante), 202);
    }
}
