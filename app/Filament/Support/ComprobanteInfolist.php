<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Models\Comprobante;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;

final class ComprobanteInfolist
{
    /**
     * @return array<int, Component>
     */
    public static function secciones(): array
    {
        return [
            Section::make('Datos generales')->schema([
                TextEntry::make('tipo')->label('Tipo')->badge()->formatStateUsing(fn (string $state): string => ComprobanteFormato::etiquetaTipo($state)),
                TextEntry::make('serie'),
                TextEntry::make('correlativo'),
                TextEntry::make('estado')->badge()->color(fn (string $state): string => ComprobanteFormato::colorEstado($state))->formatStateUsing(fn (string $state): string => ComprobanteFormato::etiquetaEstado($state)),
                TextEntry::make('fecha_emision')->label('Fecha de emisión')->date(),
                TextEntry::make('fecha_vencimiento')->label('Fecha de vencimiento')->date()->visible(fn (Comprobante $record): bool => filled($record->fecha_vencimiento)),
                TextEntry::make('forma_pago')->label('Forma de pago'),
                TextEntry::make('moneda'),
                TextEntry::make('intentos_envio')->label('Intentos de envío'),
                TextEntry::make('ultimo_error')->label('Último error')->color('danger')->columnSpanFull()->visible(fn (Comprobante $record): bool => filled($record->ultimo_error)),
            ])->columns(4),

            Section::make('Receptor')->schema([
                TextEntry::make('receptor_tipo_documento')->label('Tipo de documento'),
                TextEntry::make('receptor_numero_documento')->label('Número de documento'),
                TextEntry::make('receptor_razon_social')->label('Razón social / Nombre'),
                TextEntry::make('receptor_direccion')->label('Dirección')->visible(fn (Comprobante $record): bool => filled($record->receptor_direccion)),
                TextEntry::make('receptor_email')->label('Correo')->visible(fn (Comprobante $record): bool => filled($record->receptor_email)),
            ])->columns(3),

            Section::make('Totales')->schema([
                TextEntry::make('op_gravada')->label('Op. gravada')->money('PEN'),
                TextEntry::make('op_exonerada')->label('Op. exonerada')->money('PEN'),
                TextEntry::make('op_inafecta')->label('Op. inafecta')->money('PEN'),
                TextEntry::make('op_gratuita')->label('Op. gratuita')->money('PEN'),
                TextEntry::make('total_igv')->label('Total IGV')->money('PEN'),
                TextEntry::make('total_descuentos')->label('Descuentos')->money('PEN'),
                TextEntry::make('total')->money('PEN')->weight('bold'),
            ])->columns(4),

            Section::make('Ítems')->schema([
                RepeatableEntry::make('items')->hiddenLabel()->schema([
                    TextEntry::make('descripcion')->columnSpan(2),
                    TextEntry::make('cantidad'),
                    TextEntry::make('valor_unitario')->label('Valor unit.')->money('PEN'),
                    TextEntry::make('monto_igv')->label('IGV')->money('PEN'),
                    TextEntry::make('monto_valor_venta')->label('Valor venta')->money('PEN'),
                ])->columns(6),
            ]),

            Section::make('Tributos')->schema([
                RepeatableEntry::make('tributos')->hiddenLabel()->schema([
                    TextEntry::make('tipo_tributo')->label('Tipo'),
                    TextEntry::make('base_imponible')->label('Base imponible')->money('PEN'),
                    TextEntry::make('monto')->money('PEN'),
                ])->columns(3),
            ])->visible(fn (Comprobante $record): bool => $record->tributos->isNotEmpty()),

            Section::make('Trazabilidad SUNAT')->schema([
                TextEntry::make('xml_sha256')->label('SHA-256 XML')->copyable()->visible(fn (Comprobante $record): bool => filled($record->xml_sha256)),
                TextEntry::make('cdr_sha256')->label('SHA-256 CDR')->copyable()->visible(fn (Comprobante $record): bool => filled($record->cdr_sha256)),
                RepeatableEntry::make('enviosSunat')->label('Historial de envíos')->schema([
                    TextEntry::make('intento'),
                    TextEntry::make('entorno'),
                    TextEntry::make('codigo_respuesta_sunat')->label('Código SUNAT'),
                    TextEntry::make('descripcion_respuesta_sunat')->label('Descripción')->columnSpan(2),
                    TextEntry::make('duracion_ms')->label('Duración (ms)'),
                    TextEntry::make('error_tecnico')->label('Error técnico')->color('danger')->columnSpanFull()->visible(fn (mixed $state): bool => filled($state)),
                    TextEntry::make('created_at')->label('Fecha')->dateTime(),
                ])->columns(4)->visible(fn (Comprobante $record): bool => $record->enviosSunat->isNotEmpty()),
            ]),

            Section::make('Eventos')->schema([
                RepeatableEntry::make('eventos')->hiddenLabel()->schema([
                    TextEntry::make('tipo_evento')->label('Evento'),
                    TextEntry::make('actor'),
                    TextEntry::make('created_at')->label('Fecha')->dateTime(),
                ])->columns(3),
            ])->visible(fn (Comprobante $record): bool => $record->eventos->isNotEmpty()),
        ];
    }
}
