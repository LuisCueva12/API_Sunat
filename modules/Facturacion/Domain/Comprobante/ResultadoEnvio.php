<?php

declare(strict_types=1);

namespace Modules\Facturacion\Domain\Comprobante;

final class ResultadoEnvio
{
    private function __construct(
        public readonly bool $huboRespuestaSunat,
        public readonly ?string $codigoRespuesta,
        public readonly ?string $descripcionRespuesta,
        /** @var string[] */
        public readonly array $notas,
        public readonly ?string $cdrZipBase64,
        public readonly ?string $errorTecnico,
    ) {}

    /**
     * @param  string[]  $notas
     */
    public static function conRespuestaSunat(
        string $codigo,
        string $descripcion,
        array $notas,
        string $cdrZipBase64,
    ): self {
        return new self(
            huboRespuestaSunat: true,
            codigoRespuesta: $codigo,
            descripcionRespuesta: $descripcion,
            notas: $notas,
            cdrZipBase64: $cdrZipBase64,
            errorTecnico: null,
        );
    }

    public static function errorTecnico(string $mensaje): self
    {
        return new self(
            huboRespuestaSunat: false,
            codigoRespuesta: null,
            descripcionRespuesta: null,
            notas: [],
            cdrZipBase64: null,
            errorTecnico: $mensaje,
        );
    }

    public function esAceptado(): bool
    {
        return $this->huboRespuestaSunat && $this->codigoRespuesta === '0' && $this->notas === [];
    }

    public function esAceptadoConObservaciones(): bool
    {
        return $this->huboRespuestaSunat && $this->codigoRespuesta === '0' && $this->notas !== [];
    }

    public function esRechazado(): bool
    {
        return $this->huboRespuestaSunat && $this->codigoRespuesta !== '0';
    }
}
