<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Facturacion\Domain\Puertos\AsignadorCorrelativo;
use Modules\Facturacion\Domain\Puertos\GeneradorId;
use Modules\Facturacion\Domain\Puertos\GestorTransacciones;
use Modules\Facturacion\Domain\Puertos\RepositorioComprobante;
use Modules\Facturacion\Domain\Validacion\ValidadorComprobante;
use Modules\Facturacion\Domain\Validacion\ValidadorFactura;
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
 * ValidadorComprobante se resuelve aquí a ValidadorFactura como default
 * temporal: cuando existan Boleta/NotaCredito/NotaDebito, esto pasa a un
 * factory por TipoComprobante en vez de un binding fijo.
 */
class DomainServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AsignadorCorrelativo::class, AsignadorCorrelativoPostgres::class);
        $this->app->bind(GeneradorId::class, GeneradorIdUuid::class);
        $this->app->bind(GestorTransacciones::class, GestorTransaccionesDb::class);
        $this->app->bind(RepositorioComprobante::class, RepositorioComprobanteEloquent::class);
        $this->app->bind(ValidadorComprobante::class, ValidadorFactura::class);
    }
}
