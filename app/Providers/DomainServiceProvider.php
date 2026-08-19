<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\DespachadorProcesamientoQueue;
use Illuminate\Support\ServiceProvider;
use Modules\Facturacion\Application\CasosDeUso\EmitirBoleta;
use Modules\Facturacion\Application\CasosDeUso\EmitirFactura;
use Modules\Facturacion\Application\CasosDeUso\EmitirNotaCredito;
use Modules\Facturacion\Application\CasosDeUso\EmitirNotaDebito;
use Modules\Facturacion\Domain\Puertos\AlmacenPrivado;
use Modules\Facturacion\Domain\Puertos\AsignadorCorrelativo;
use Modules\Facturacion\Domain\Puertos\DespachadorProcesamiento;
use Modules\Facturacion\Domain\Puertos\FabricaEnviadorComprobante;
use Modules\Facturacion\Domain\Puertos\GeneradorId;
use Modules\Facturacion\Domain\Puertos\GeneradorXmlFirmado;
use Modules\Facturacion\Domain\Puertos\GestorClientesOAuth;
use Modules\Facturacion\Domain\Puertos\GestorTransacciones;
use Modules\Facturacion\Domain\Puertos\ProveedorDatosSunat;
use Modules\Facturacion\Domain\Puertos\RepositorioCertificado;
use Modules\Facturacion\Domain\Puertos\RepositorioComprobante;
use Modules\Facturacion\Domain\Puertos\RepositorioCredencialSunat;
use Modules\Facturacion\Domain\Puertos\RepositorioEmpresa;
use Modules\Facturacion\Domain\Puertos\RepositorioIntegracionApi;
use Modules\Facturacion\Domain\Puertos\RepositorioSerie;
use Modules\Facturacion\Domain\Validacion\ValidadorBoleta;
use Modules\Facturacion\Domain\Validacion\ValidadorComprobante;
use Modules\Facturacion\Domain\Validacion\ValidadorFactura;
use Modules\Facturacion\Domain\Validacion\ValidadorNotaCredito;
use Modules\Facturacion\Domain\Validacion\ValidadorNotaDebito;
use Modules\Facturacion\Infrastructure\Passport\GestorClientesOAuthPassport;
use Modules\Facturacion\Infrastructure\Persistencia\Eloquent\AsignadorCorrelativoPostgres;
use Modules\Facturacion\Infrastructure\Persistencia\Eloquent\GestorTransaccionesDb;
use Modules\Facturacion\Infrastructure\Persistencia\Eloquent\RepositorioCertificadoEloquent;
use Modules\Facturacion\Infrastructure\Persistencia\Eloquent\RepositorioComprobanteEloquent;
use Modules\Facturacion\Infrastructure\Persistencia\Eloquent\RepositorioCredencialSunatEloquent;
use Modules\Facturacion\Infrastructure\Persistencia\Eloquent\RepositorioEmpresaEloquent;
use Modules\Facturacion\Infrastructure\Persistencia\Eloquent\RepositorioIntegracionApiEloquent;
use Modules\Facturacion\Infrastructure\Persistencia\Eloquent\RepositorioSerieEloquent;
use Modules\Facturacion\Infrastructure\Persistencia\GeneradorIdUuid;
use Modules\Facturacion\Infrastructure\Storage\AlmacenPrivadoStorage;
use Modules\Facturacion\Infrastructure\Sunat\Greenter\FabricaClienteSunatGreenter;
use Modules\Facturacion\Infrastructure\Sunat\Greenter\GeneradorXmlFirmadoGreenter;
use Modules\Facturacion\Infrastructure\Sunat\ProveedorDatosSunatEloquent;

class DomainServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AsignadorCorrelativo::class, AsignadorCorrelativoPostgres::class);
        $this->app->bind(GeneradorId::class, GeneradorIdUuid::class);
        $this->app->bind(GestorTransacciones::class, GestorTransaccionesDb::class);
        $this->app->bind(RepositorioComprobante::class, RepositorioComprobanteEloquent::class);
        $this->app->bind(GeneradorXmlFirmado::class, GeneradorXmlFirmadoGreenter::class);
        $this->app->bind(AlmacenPrivado::class, AlmacenPrivadoStorage::class);
        $this->app->bind(ProveedorDatosSunat::class, ProveedorDatosSunatEloquent::class);
        $this->app->bind(FabricaEnviadorComprobante::class, FabricaClienteSunatGreenter::class);
        $this->app->bind(DespachadorProcesamiento::class, DespachadorProcesamientoQueue::class);
        $this->app->bind(RepositorioEmpresa::class, RepositorioEmpresaEloquent::class);
        $this->app->bind(RepositorioSerie::class, RepositorioSerieEloquent::class);
        $this->app->bind(RepositorioCertificado::class, RepositorioCertificadoEloquent::class);
        $this->app->bind(RepositorioCredencialSunat::class, RepositorioCredencialSunatEloquent::class);
        $this->app->bind(RepositorioIntegracionApi::class, RepositorioIntegracionApiEloquent::class);
        $this->app->bind(GestorClientesOAuth::class, GestorClientesOAuthPassport::class);

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
