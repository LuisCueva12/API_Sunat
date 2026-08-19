<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Validation\Rule;
use Modules\Facturacion\Domain\Comprobante\MotivoNotaCredito;

class EmitirNotaCreditoRequest extends EmitirComprobanteRequest
{
    /**
     * @return array<string, mixed>
     */
    protected function reglasEspecificas(): array
    {
        return [
            'comprobante_referencia_id' => ['required', 'uuid'],
            'codigo_motivo' => ['required', 'string', Rule::enum(MotivoNotaCredito::class)],
            'descripcion_motivo' => ['required', 'string', 'max:255'],
        ];
    }
}
