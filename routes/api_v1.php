<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\BoletaController;
use App\Http\Controllers\Api\V1\ComprobanteController;
use App\Http\Controllers\Api\V1\FacturaController;
use App\Http\Controllers\Api\V1\NotaCreditoController;
use App\Http\Controllers\Api\V1\NotaDebitoController;
use Illuminate\Support\Facades\Route;

// Emisión: específica por tipo — el contrato de entrada difiere
// genuinamente entre ellos (ver docs/04_API.md).
Route::middleware(['api.key', 'api.scope:comprobantes:crear', 'api.idempotencia'])->group(function () {
    Route::post('/facturas', [FacturaController::class, 'store']);
    Route::post('/boletas', [BoletaController::class, 'store']);
    Route::post('/notas-credito', [NotaCreditoController::class, 'store']);
    Route::post('/notas-debito', [NotaDebitoController::class, 'store']);
});

// Consulta y ciclo de vida: genérico — una vez creado, un comprobante es
// un recurso uniforme sin importar su tipo original.
Route::middleware(['api.key', 'api.scope:comprobantes:leer'])->group(function () {
    Route::get('/comprobantes', [ComprobanteController::class, 'index']);
    Route::get('/comprobantes/{id}', [ComprobanteController::class, 'show']);
    Route::get('/comprobantes/{id}/estado', [ComprobanteController::class, 'estado']);
});

Route::middleware(['api.key', 'api.scope:comprobantes:reintentar'])->group(function () {
    Route::post('/comprobantes/{id}/reintentar', [ComprobanteController::class, 'reintentar']);
});
