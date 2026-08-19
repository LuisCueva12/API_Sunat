<?php

declare(strict_types=1);

namespace Modules\Clientes\Domain\Puertos;

use Modules\Clientes\Domain\Cliente;
use Modules\Clientes\Domain\TipoDocumentoCliente;

interface RepositorioCliente
{
    public function guardar(Cliente $cliente): void;

    public function buscarPorId(string $empresaId, string $id): ?Cliente;

    public function buscarPorDocumento(string $empresaId, TipoDocumentoCliente $tipoDocumento, string $numeroDocumento): ?Cliente;

    public function existe(string $empresaId, TipoDocumentoCliente $tipoDocumento, string $numeroDocumento): bool;
}
