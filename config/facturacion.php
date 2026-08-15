<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Almacenamiento de documentos tributarios
    |--------------------------------------------------------------------------
    |
    | Disco (ver config/filesystems.php) donde se guardan XML, CDR y PDF de
    | comprobantes. Nunca debe apuntar al disco "public".
    |
    */

    'storage_disk' => env('FACTURACION_STORAGE_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | SUNAT
    |--------------------------------------------------------------------------
    |
    | Configuración central de entornos SUNAT. Las credenciales reales
    | (usuario SOL, clave SOL, certificado) viven cifradas en BD por empresa
    | (tabla credenciales_sunat / certificados_digitales), nunca aquí.
    | Este archivo solo define URLs/timeouts, nunca secretos.
    |
    */

    'sunat' => [
        'entorno_por_defecto' => env('SUNAT_ENTORNO', 'beta'), // beta | produccion
        'timeout_segundos' => (int) env('SUNAT_TIMEOUT_SEGUNDOS', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Idempotencia
    |--------------------------------------------------------------------------
    */

    'idempotencia' => [
        'ttl_horas' => (int) env('FACTURACION_IDEMPOTENCIA_TTL_HORAS', 24),
    ],

    /*
    |--------------------------------------------------------------------------
    | Reintentos de envío a SUNAT
    |--------------------------------------------------------------------------
    */

    'reintentos' => [
        'maximo_intentos' => (int) env('FACTURACION_MAX_INTENTOS_ENVIO', 5),
    ],

];
