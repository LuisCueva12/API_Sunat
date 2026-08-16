<?php

declare(strict_types=1);

namespace Modules\Facturacion\Domain\Excepciones;

use DomainException;

/**
 * La empresa no tiene certificado activo o credenciales SUNAT para el
 * entorno solicitado — un problema de configuración operativa, no de los
 * datos de un comprobante puntual (por eso no es ComprobanteInvalidoException).
 */
final class ConfiguracionSunatInvalidaException extends DomainException {}
