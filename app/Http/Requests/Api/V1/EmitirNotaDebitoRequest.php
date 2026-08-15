<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

class EmitirNotaDebitoRequest extends EmitirComprobanteRequest
{
    /**
     * @return array<string, mixed>
     */
    protected function reglasEspecificas(): array
    {
        return [
            'comprobante_referencia_id' => ['required', 'uuid'],
            'codigo_motivo' => ['required', 'string', 'max:2'],
            'descripcion_motivo' => ['required', 'string', 'max:255'],
        ];
    }
}
