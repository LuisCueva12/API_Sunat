<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Clientes\Domain\Puertos\RepositorioCliente;
use Modules\Clientes\Infrastructure\Persistencia\Eloquent\RepositorioClienteEloquent;

class ClientesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(RepositorioCliente::class, RepositorioClienteEloquent::class);
    }
}
