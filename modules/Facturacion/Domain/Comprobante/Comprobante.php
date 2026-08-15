<?php

declare(strict_types=1);

namespace Modules\Facturacion\Domain\Comprobante;

use DateTimeImmutable;
use Modules\Facturacion\Domain\Excepciones\TransicionEstadoInvalidaException;
use Modules\Facturacion\Domain\ValueObjects\DocumentoIdentidad;
use Modules\Facturacion\Domain\ValueObjects\Moneda;
use Modules\Facturacion\Domain\ValueObjects\NumeroComprobante;

/**
 * Agregado raíz. Un único tipo de entidad para los 4 tipos documentales de
 * la V1 (discriminados por TipoComprobante) — el pipeline técnico es
 * idéntico entre ellos; las reglas específicas por tipo viven en
 * Domain/Validacion, no aquí. Ver docs/01_ARQUITECTURA.md §3.
 */
final class Comprobante
{
    /** @var ItemComprobante[] */
    private array $items = [];

    /** @var Tributo[] */
    private array $tributos = [];

    private function __construct(
        private readonly string $id,
        private readonly string $empresaId,
        private readonly TipoComprobante $tipo,
        private readonly NumeroComprobante $numero,
        private EstadoComprobante $estado,
        private readonly Moneda $moneda,
        private readonly DocumentoIdentidad $receptorDocumento,
        private readonly string $receptorRazonSocial,
        private readonly DateTimeImmutable $fechaEmision,
        private readonly ?ReferenciaComprobante $referencia,
        private ?TotalesComprobante $totales,
        private int $intentosEnvio,
    ) {}

    public static function registrar(
        string $id,
        string $empresaId,
        TipoComprobante $tipo,
        NumeroComprobante $numero,
        Moneda $moneda,
        DocumentoIdentidad $receptorDocumento,
        string $receptorRazonSocial,
        DateTimeImmutable $fechaEmision,
        ?ReferenciaComprobante $referencia = null,
    ): self {
        return new self(
            id: $id,
            empresaId: $empresaId,
            tipo: $tipo,
            numero: $numero,
            estado: EstadoComprobante::Registrado,
            moneda: $moneda,
            receptorDocumento: $receptorDocumento,
            receptorRazonSocial: $receptorRazonSocial,
            fechaEmision: $fechaEmision,
            referencia: $referencia,
            totales: null,
            intentosEnvio: 0,
        );
    }

    /**
     * Reconstruye un Comprobante desde su estado ya persistido (cualquier
     * EstadoComprobante, no solo Registrado) — usado por los repositorios al
     * leer de la base de datos. registrar() es solo para comprobantes nuevos.
     */
    public static function reconstituir(
        string $id,
        string $empresaId,
        TipoComprobante $tipo,
        NumeroComprobante $numero,
        EstadoComprobante $estado,
        Moneda $moneda,
        DocumentoIdentidad $receptorDocumento,
        string $receptorRazonSocial,
        DateTimeImmutable $fechaEmision,
        ?ReferenciaComprobante $referencia,
        ?TotalesComprobante $totales,
        int $intentosEnvio,
    ): self {
        return new self(
            id: $id,
            empresaId: $empresaId,
            tipo: $tipo,
            numero: $numero,
            estado: $estado,
            moneda: $moneda,
            receptorDocumento: $receptorDocumento,
            receptorRazonSocial: $receptorRazonSocial,
            fechaEmision: $fechaEmision,
            referencia: $referencia,
            totales: $totales,
            intentosEnvio: $intentosEnvio,
        );
    }

    public function agregarItem(ItemComprobante $item): void
    {
        $this->items[] = $item;
    }

    public function agregarTributo(Tributo $tributo): void
    {
        $this->tributos[] = $tributo;
    }

    public function definirTotales(TotalesComprobante $totales): void
    {
        $this->totales = $totales;
    }

    public function transicionarA(EstadoComprobante $destino): void
    {
        if (! $this->estado->puedeTransicionarA($destino)) {
            throw new TransicionEstadoInvalidaException(
                "No se puede transicionar de {$this->estado->value} a {$destino->value} (comprobante {$this->id})."
            );
        }

        $this->estado = $destino;
    }

    public function marcarProcesando(): void
    {
        $this->transicionarA(EstadoComprobante::Procesando);
    }

    public function marcarAceptado(): void
    {
        $this->transicionarA(EstadoComprobante::Aceptado);
    }

    public function marcarAceptadoConObservaciones(): void
    {
        $this->transicionarA(EstadoComprobante::AceptadoConObservaciones);
    }

    public function marcarRechazado(): void
    {
        $this->transicionarA(EstadoComprobante::Rechazado);
    }

    public function marcarError(): void
    {
        $this->transicionarA(EstadoComprobante::Error);
        $this->intentosEnvio++;
    }

    public function reintentar(): void
    {
        $this->transicionarA(EstadoComprobante::Procesando);
    }

    public function esReintentable(): bool
    {
        return $this->estado === EstadoComprobante::Error;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function empresaId(): string
    {
        return $this->empresaId;
    }

    public function tipo(): TipoComprobante
    {
        return $this->tipo;
    }

    public function numero(): NumeroComprobante
    {
        return $this->numero;
    }

    public function estado(): EstadoComprobante
    {
        return $this->estado;
    }

    public function moneda(): Moneda
    {
        return $this->moneda;
    }

    public function receptorDocumento(): DocumentoIdentidad
    {
        return $this->receptorDocumento;
    }

    public function receptorRazonSocial(): string
    {
        return $this->receptorRazonSocial;
    }

    public function fechaEmision(): DateTimeImmutable
    {
        return $this->fechaEmision;
    }

    public function referencia(): ?ReferenciaComprobante
    {
        return $this->referencia;
    }

    public function totales(): ?TotalesComprobante
    {
        return $this->totales;
    }

    public function intentosEnvio(): int
    {
        return $this->intentosEnvio;
    }

    /** @return ItemComprobante[] */
    public function items(): array
    {
        return $this->items;
    }

    /** @return Tributo[] */
    public function tributos(): array
    {
        return $this->tributos;
    }
}
