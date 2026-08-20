<?php

declare(strict_types=1);

namespace App\Http\Requests\Facturador;

use App\Models\Usuario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class EmitirVentaRequest extends FormRequest
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
        $tipo = (string) $this->input('tipo');

        return [
            'tipo' => ['required', Rule::in(['BOLETA', 'FACTURA'])],
            'serie' => [
                'required',
                'string',
                'size:4',
                Rule::exists('series', 'serie')->where(fn ($query) => $query
                    ->where('empresa_id', $usuario->empresa_id)
                    ->where('tipo_comprobante', $tipo)
                    ->where('activa', true)),
            ],
            'receptor_tipo_documento' => [
                'required',
                Rule::in($tipo === 'FACTURA'
                    ? ['RUC']
                    : ['SIN_DOCUMENTO', 'DNI', 'CARNET_EXTRANJERIA', 'PASAPORTE']),
            ],
            'receptor_numero_documento' => ['nullable', 'string', 'max:15', 'required_unless:receptor_tipo_documento,SIN_DOCUMENTO'],
            'receptor_razon_social' => ['required', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.descripcion' => ['required', 'string', 'max:500'],
            'items.*.unidad_medida' => ['required', Rule::in(['NIU', 'ZZ'])],
            'items.*.cantidad' => ['required', 'numeric', 'gt:0'],
            'items.*.valor_unitario' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'items.*.descuento' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'items.*.codigo_producto' => ['nullable', 'string', 'max:50'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'serie.exists' => 'Selecciona una serie activa para este comprobante.',
            'receptor_tipo_documento.in' => 'El documento elegido no corresponde al tipo de comprobante.',
            'items.required' => 'Agrega al menos un producto o servicio.',
            'items.min' => 'Agrega al menos un producto o servicio.',
        ];
    }
}
