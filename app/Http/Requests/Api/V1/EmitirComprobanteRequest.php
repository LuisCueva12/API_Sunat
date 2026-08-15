<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Solo formato/presencia (ver docs/01_ARQUITECTURA.md punto 29 del brief
 * original: validación de formato vs. reglas de negocio). Que el receptor
 * deba tener RUC en factura, o que una nota exija referencia válida, son
 * reglas de negocio — viven en los Validador* de Domain, no aquí.
 */
class EmitirComprobanteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // autenticación/autorización ya resuelta por middleware
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge($this->reglasBase(), $this->reglasEspecificas());
    }

    /**
     * @return array<string, mixed>
     */
    protected function reglasBase(): array
    {
        return [
            'serie' => ['required', 'string', 'size:4'],
            'receptor_tipo_documento' => ['required', 'string', Rule::in(['0', '1', '4', '6', '7'])],
            'receptor_numero_documento' => ['required_unless:receptor_tipo_documento,0', 'nullable', 'string', 'max:15'],
            'receptor_razon_social' => ['required', 'string', 'max:255'],
            'moneda' => ['sometimes', 'string', Rule::in(['PEN', 'USD'])],
            'items' => ['required', 'array', 'min:1'],
            'items.*.descripcion' => ['required', 'string', 'max:500'],
            'items.*.unidad_medida' => ['required', 'string', 'max:3'],
            'items.*.cantidad' => ['required', 'numeric', 'gt:0'],
            'items.*.valor_unitario' => ['required', 'string', 'regex:/^\d+(\.\d{1,2})?$/'],
            'items.*.tipo_afectacion_igv' => ['required', 'string'],
            'items.*.codigo_producto' => ['nullable', 'string', 'max:50'],
            'items.*.descuento' => ['nullable', 'string', 'regex:/^\d+(\.\d{1,2})?$/'],
        ];
    }

    /**
     * Sobrescrita por Nota de Crédito/Débito para exigir referencia+motivo.
     *
     * @return array<string, mixed>
     */
    protected function reglasEspecificas(): array
    {
        return [];
    }
}
