<?php

declare(strict_types=1);

namespace Modules\Facturacion\Domain\Tributario;

use Modules\Facturacion\Domain\Comprobante\ItemComprobante;
use Modules\Facturacion\Domain\Comprobante\TotalesComprobante;
use Modules\Facturacion\Domain\Comprobante\Tributo;
use Modules\Facturacion\Domain\Excepciones\ComprobanteInvalidoException;
use Modules\Facturacion\Domain\ValueObjects\Dinero;

final class CalculadorTributos
{
    private const TASA_IGV = 0.18;

    private const CODIGOS_AFECTACION_SOPORTADOS = ['10'];

    public function calcularItem(
        int $numeroOrden,
        string $descripcion,
        string $unidadMedida,
        float $cantidad,
        Dinero $valorUnitario,
        string $tipoAfectacionIgv,
        ?string $codigoProducto = null,
        ?Dinero $descuento = null,
    ): ItemComprobante {
        if (! in_array($tipoAfectacionIgv, self::CODIGOS_AFECTACION_SOPORTADOS, true)) {
            throw new ComprobanteInvalidoException(
                "El tipo de afectación IGV '{$tipoAfectacionIgv}' no está soportado en V1 (solo operación gravada, código '10')."
            );
        }

        $descuento ??= Dinero::cero();
        $valorVenta = $valorUnitario->multiplicarPor($cantidad)->restar($descuento);
        $montoIgv = $valorVenta->multiplicarPor(self::TASA_IGV);
        $precioUnitario = $cantidad > 0
            ? $valorVenta->sumar($montoIgv)->multiplicarPor(1 / $cantidad)
            : Dinero::cero();

        return new ItemComprobante(
            numeroOrden: $numeroOrden,
            descripcion: $descripcion,
            unidadMedida: $unidadMedida,
            cantidad: $cantidad,
            valorUnitario: $valorUnitario,
            precioUnitario: $precioUnitario,
            tipoAfectacionIgv: $tipoAfectacionIgv,
            montoIgv: $montoIgv,
            montoValorVenta: $valorVenta,
            descuento: $descuento,
            codigoProducto: $codigoProducto,
        );
    }

    /**
     * @param  ItemComprobante[]  $items
     * @return array{totales: TotalesComprobante, tributos: Tributo[]}
     */
    public function calcularTotales(array $items): array
    {
        $opGravada = Dinero::cero();
        $totalIgv = Dinero::cero();
        $totalDescuentos = Dinero::cero();

        foreach ($items as $item) {
            $opGravada = $opGravada->sumar($item->montoValorVenta());
            $totalIgv = $totalIgv->sumar($item->montoIgv());
            $totalDescuentos = $totalDescuentos->sumar($item->descuento());
        }

        $total = $opGravada->sumar($totalIgv);

        $tributos = $totalIgv->esCero() ? [] : [
            new Tributo(tipoTributo: 'IGV', codigo: '1000', baseImponible: $opGravada, monto: $totalIgv),
        ];

        return [
            'totales' => new TotalesComprobante(
                opGravada: $opGravada,
                opExonerada: Dinero::cero(),
                opInafecta: Dinero::cero(),
                opGratuita: Dinero::cero(),
                totalIgv: $totalIgv,
                totalDescuentos: $totalDescuentos,
                total: $total,
            ),
            'tributos' => $tributos,
        ];
    }
}
