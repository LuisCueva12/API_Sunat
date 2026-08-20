<?php

declare(strict_types=1);

namespace App\Services\Webhooks;

use InvalidArgumentException;

final class ValidadorUrlWebhook
{
    public function validar(string $url): void
    {
        $this->resolverDireccionPublica($url);
    }

    public function resolverDireccionPublica(string $url): string
    {
        $partes = parse_url($url);
        $host = $partes['host'] ?? null;

        if (($partes['scheme'] ?? null) !== 'https' || ! is_string($host) || $host === '') {
            throw new InvalidArgumentException('El webhook debe usar una URL HTTPS válida.');
        }

        if (isset($partes['user']) || isset($partes['pass'])) {
            throw new InvalidArgumentException('La URL del webhook no puede incluir credenciales.');
        }

        $direcciones = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : gethostbynamel($host);

        if ($direcciones === false || $direcciones === []) {
            throw new InvalidArgumentException('No se pudo resolver el host del webhook.');
        }

        foreach ($direcciones as $direccion) {
            if (filter_var(
                $direccion,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
            ) === false) {
                throw new InvalidArgumentException('El webhook no puede apuntar a una red privada o reservada.');
            }
        }

        return $direcciones[0];
    }
}
