<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Facturacion\Application\CasosDeUso\EmitirBoleta;
use Modules\Facturacion\Application\CasosDeUso\EmitirFactura;
use Modules\Facturacion\Application\CasosDeUso\EmitirNotaCredito;
use Modules\Facturacion\Application\CasosDeUso\EmitirNotaDebito;
use Modules\Facturacion\Domain\Puertos\AsignadorCorrelativo;
use Modules\Facturacion\Domain\Puertos\GeneradorId;
use Modules\Facturacion\Domain\Puertos\GestorTransacciones;
use Modules\Facturacion\Domain\Puertos\RepositorioComprobante;
use Modules\Facturacion\Domain\Validacion\ValidadorBoleta;
use Modules\Facturacion\Domain\Validacion\ValidadorComprobante;
use Modules\Facturacion\Domain\Validacion\ValidadorFactura;
use Modules\Facturacion\Domain\Validacion\ValidadorNotaCredito;
use Modules\Facturacion\Domain\Validacion\ValidadorNotaDebito;
use Modules\Facturacion\Infrastructure\Persistencia\Eloquent\AsignadorCorrelativoPostgres;
use Modules\Facturacion\Infrastructure\Persistencia\Eloquent\GestorTransaccionesDb;
use Modules\Facturacion\Infrastructure\Persistencia\Eloquent\RepositorioComprobanteEloquent;
use Modules\Facturacion\Infrastructure\Persistencia\GeneradorIdUuid;

/**
 * Único lugar de la aplicación donde se conocen a la vez un puerto de
 * Domain y su adaptador de Infrastructure. Si mañana cambia la
 * implementación (otro storage, otro proveedor SUNAT), el cambio se
 * localiza aquí — nada más en la app debe enterarse.
 *
 * ValidadorComprobante no tiene un binding único: cada caso de uso Emitir*
 * recibe el suyo por binding contextual, porque cada tipo de comprobante
 * tiene sus propias reglas (ver Domain/Validacion).
 */
class DomainServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AsignadorCorrelativo::class, AsignadorCorrelativoPostgres::class);
        $this->app->bind(GeneradorId::class, GeneradorIdUuid::class);
        $this->app->bind(GestorTransacciones::class, GestorTransaccionesDb::class);
        $this->app->bind(RepositorioComprobante::class, RepositorioComprobanteEloquent::class);

        $this->app->when(EmitirFactura::class)
            ->needs(ValidadorComprobante::class)
            ->give(ValidadorFactura::class);

        $this->app->when(EmitirBoleta::class)
            ->needs(ValidadorComprobante::class)
            ->give(ValidadorBoleta::class);

        $this->app->when(EmitirNotaCredito::class)
            ->needs(ValidadorComprobante::class)
            ->give(ValidadorNotaCredito::class);

        $this->app->when(EmitirNotaDebito::class)
            ->needs(ValidadorComprobante::class)
            ->give(ValidadorNotaDebito::class);
    }
}
