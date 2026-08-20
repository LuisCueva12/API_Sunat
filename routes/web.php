<?php

declare(strict_types=1);

use App\Http\Controllers\Facturador\AutenticacionController;
use App\Http\Controllers\Facturador\ClienteController;
use App\Http\Controllers\Facturador\ComprobanteController;
use App\Http\Controllers\Facturador\CuentaController;
use App\Http\Controllers\Facturador\InicioController;
use App\Http\Controllers\Facturador\ProductoServicioController;
use App\Http\Controllers\Facturador\VentaController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('facturador.inicio');
});

Route::prefix('app')->name('facturador.')->group(function (): void {
    Route::get('/login', [AutenticacionController::class, 'create'])->name('login');
    Route::post('/login', [AutenticacionController::class, 'store'])->name('login.store');

    Route::middleware('facturador')->group(function (): void {
        Route::get('/', InicioController::class)->name('inicio');
        Route::post('/salir', [AutenticacionController::class, 'destroy'])->name('logout');

        Route::get('/nueva-venta', [VentaController::class, 'create'])->name('ventas.create');
        Route::post('/nueva-venta', [VentaController::class, 'store'])->name('ventas.store');
        Route::get('/ventas/{id}/confirmacion', [VentaController::class, 'confirmacion'])->name('ventas.confirmacion');
        Route::get('/ventas/{id}/estado', [VentaController::class, 'estado'])->name('ventas.estado');
        Route::get('/buscar/clientes', [VentaController::class, 'buscarClientes'])->name('buscar.clientes');
        Route::get('/buscar/productos', [VentaController::class, 'buscarProductos'])->name('buscar.productos');

        Route::get('/comprobantes', [ComprobanteController::class, 'index'])->name('comprobantes.index');
        Route::get('/comprobantes/{id}', [ComprobanteController::class, 'show'])->name('comprobantes.show');
        Route::get('/clientes', [ClienteController::class, 'index'])->name('clientes.index');
        Route::get('/productos', [ProductoServicioController::class, 'index'])->name('productos.index');
        Route::post('/productos', [ProductoServicioController::class, 'store'])->name('productos.store');
        Route::get('/mi-cuenta', CuentaController::class)->name('cuenta');
    });
});
