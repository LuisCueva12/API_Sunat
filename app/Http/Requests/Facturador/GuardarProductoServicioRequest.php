<?php

declare(strict_types=1);

namespace App\Http\Requests\Facturador;

use App\Models\Usuario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class GuardarProductoServicioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof Usuario && $this->user()->empresa_id !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var Usuario $usuario */
        $usuario = $this->user();

        return [
            'codigo' => ['nullable', 'string', 'max:50', Rule::unique('productos_servicios', 'codigo')->where('empresa_id', $usuario->empresa_id)],
            'nombre' => ['required', 'string', 'max:255'],
            'tipo' => ['required', Rule::in(['PRODUCTO', 'SERVICIO'])],
            'unidad_medida' => ['required', Rule::in(['NIU', 'ZZ'])],
            'valor_unitario' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
        ];
    }
}
