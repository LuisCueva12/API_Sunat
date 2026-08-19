<?php

declare(strict_types=1);

namespace Modules\Facturacion\Application\DTO;

use DateTimeImmutable;

final class CrearApiKeyInput
{
    /**
     * @param  array<int, string>  $scopes
     */
    public function __construct(
        public readonly string $empresaId,
        public readonly string $nombre,
        public readonly array $scopes,
        public readonly ?DateTimeImmutable $expiraEn = null,
    ) {}
}
