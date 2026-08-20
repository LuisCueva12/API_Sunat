<?php

declare(strict_types=1);

namespace Modules\Facturacion\Infrastructure\Persistencia\Eloquent;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Facturacion\Domain\Comprobante\Comprobante;
use Modules\Facturacion\Domain\Comprobante\ResultadoEnvio;
use Modules\Facturacion\Domain\Puertos\RegistradorTrazabilidadComprobante;

final class RegistradorTrazabilidadComprobanteEloquent implements RegistradorTrazabilidadComprobante
{
    public function registrarEvento(
        Comprobante $comprobante,
        string $tipo,
        ?string $actor = null,
        ?string $requestId = null,
        array $datos = [],
    ): void {
        DB::table('eventos_comprobante')->insert([
            'comprobante_id' => $comprobante->id(),
            'empresa_id' => $comprobante->empresaId(),
            'tipo_evento' => $tipo,
            'actor' => $actor,
            'request_id' => $requestId,
            'datos' => $datos === [] ? null : json_encode($datos, JSON_THROW_ON_ERROR),
            'created_at' => now(),
        ]);
    }

    public function registrarEnvio(
        Comprobante $comprobante,
        string $entorno,
        int $intento,
        ?ResultadoEnvio $resultado,
        ?string $rutaXml,
        ?string $rutaCdr,
        int $duracionMs,
        ?string $errorTecnico = null,
    ): void {
        DB::table('envios_sunat')->insert([
            'id' => (string) Str::uuid7(),
            'comprobante_id' => $comprobante->id(),
            'intento' => $intento,
            'entorno' => strtoupper($entorno),
            'codigo_respuesta_sunat' => $resultado?->codigoRespuesta,
            'descripcion_respuesta_sunat' => $resultado?->descripcionRespuesta,
            'notas_sunat' => $resultado === null ? null : json_encode($resultado->notas, JSON_THROW_ON_ERROR),
            'xml_path' => $rutaXml,
            'cdr_path' => $rutaCdr,
            'duracion_ms' => $duracionMs,
            'error_tecnico' => $errorTecnico,
            'created_at' => now(),
        ]);
    }
}
