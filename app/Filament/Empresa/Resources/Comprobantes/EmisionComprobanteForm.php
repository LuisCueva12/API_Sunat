<?php

declare(strict_types=1);

namespace App\Filament\Empresa\Resources\Comprobantes;

use App\Models\Cliente;
use App\Models\Serie;
use App\Models\Usuario;
use Filament\Facades\Filament;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

final class EmisionComprobanteForm
{
    public static function configurar(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Comprobante')->schema([
                Select::make('tipo')
                    ->label('¿Qué deseas emitir?')
                    ->options([
                        'BOLETA' => 'Boleta',
                        'FACTURA' => 'Factura',
                    ])
                    ->default('BOLETA')
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (?string $state, Set $set): void {
                        $set('serie', self::primeraSerieActiva($state));
                        $set('cliente_id', null);
                        $set('receptor_tipo_documento', $state === 'FACTURA' ? 'RUC' : 'SIN_DOCUMENTO');
                        $set('receptor_numero_documento', '');
                        $set('receptor_razon_social', $state === 'FACTURA' ? '' : 'Cliente varios');
                    }),
                Select::make('serie')
                    ->label('Serie')
                    ->options(fn (Get $get): array => self::seriesActivas($get('tipo')))
                    ->default(fn (): ?string => self::primeraSerieActiva('BOLETA'))
                    ->required()
                    ->helperText('Solo aparecen las series activas de tu empresa para este tipo.'),
            ])->columns(2),

            Section::make('Receptor')
                ->description('Puedes elegir un cliente guardado o escribir los datos directamente. No es obligatorio registrar al cliente antes.')
                ->schema([
                    Select::make('cliente_id')
                        ->label('Buscar cliente guardado (opcional)')
                        ->searchable()
                        ->searchPrompt('Busca por nombre o documento')
                        ->getSearchResultsUsing(fn (Get $get, string $search): array => self::buscarClientes($get('tipo'), $search))
                        ->getOptionLabelUsing(fn (mixed $value): ?string => self::etiquetaCliente(is_string($value) ? $value : null))
                        ->live()
                        ->afterStateUpdated(function (mixed $state, Set $set): void {
                            $cliente = self::clienteDelTenant(is_string($state) ? $state : null);

                            if ($cliente === null) {
                                return;
                            }

                            $set('receptor_tipo_documento', self::tipoDocumentoFormulario($cliente->tipo_documento));
                            $set('receptor_numero_documento', $cliente->numero_documento);
                            $set('receptor_razon_social', $cliente->razon_social);
                        })
                        ->columnSpanFull(),
                    Select::make('receptor_tipo_documento')
                        ->label('Tipo de documento')
                        ->options(fn (Get $get): array => $get('tipo') === 'FACTURA'
                            ? ['RUC' => 'RUC']
                            : [
                                'SIN_DOCUMENTO' => 'Sin documento',
                                'DNI' => 'DNI',
                                'CARNET_EXTRANJERIA' => 'Carnet de extranjería',
                                'PASAPORTE' => 'Pasaporte',
                            ])
                        ->default('SIN_DOCUMENTO')
                        ->required()
                        ->live()
                        ->afterStateUpdated(function (?string $state, Set $set): void {
                            if ($state === 'SIN_DOCUMENTO') {
                                $set('receptor_numero_documento', '');
                            }
                        }),
                    TextInput::make('receptor_numero_documento')
                        ->label('Número de documento')
                        ->maxLength(15)
                        ->required()
                        ->visible(fn (Get $get): bool => $get('receptor_tipo_documento') !== 'SIN_DOCUMENTO'),
                    TextInput::make('receptor_razon_social')
                        ->label(fn (Get $get): string => $get('tipo') === 'FACTURA' ? 'Razón social' : 'Nombre del cliente')
                        ->default('Cliente varios')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),
                ])->columns(2),

            Section::make('Ítems')->schema([
                Repeater::make('items')
                    ->hiddenLabel()
                    ->schema([
                        TextInput::make('descripcion')
                            ->label('Producto o servicio')
                            ->required()
                            ->maxLength(500)
                            ->columnSpan(4),
                        Select::make('unidad_medida')
                            ->label('Unidad')
                            ->options([
                                'NIU' => 'Unidad',
                                'ZZ' => 'Servicio',
                            ])
                            ->default('NIU')
                            ->required()
                            ->columnSpan(2),
                        TextInput::make('cantidad')
                            ->numeric()
                            ->minValue(0.001)
                            ->default(1)
                            ->required()
                            ->live(onBlur: true)
                            ->columnSpan(2),
                        TextInput::make('valor_unitario')
                            ->label('Valor unitario sin IGV')
                            ->prefix('S/')
                            ->numeric()
                            ->minValue(0.01)
                            ->step(0.01)
                            ->required()
                            ->live(onBlur: true)
                            ->columnSpan(2),
                        TextInput::make('descuento')
                            ->label('Descuento')
                            ->prefix('S/')
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01)
                            ->live(onBlur: true)
                            ->columnSpan(2),
                        Hidden::make('tipo_afectacion_igv')->default('10'),
                    ])
                    ->columns(12)
                    ->defaultItems(1)
                    ->minItems(1)
                    ->addActionLabel('Agregar otro ítem')
                    ->reorderable(false)
                    ->columnSpanFull(),
                Callout::make('Resumen de venta')
                    ->description(fn (Get $get): string => self::resumenTotales($get('items')))
                    ->icon(Heroicon::OutlinedCalculator)
                    ->color('primary')
                    ->columnSpanFull(),
                Hidden::make('moneda')->default('PEN'),
            ]),
        ]);
    }

    /** @return array<string, string> */
    private static function seriesActivas(mixed $tipo): array
    {
        if (! in_array($tipo, ['FACTURA', 'BOLETA'], true)) {
            return [];
        }

        return Serie::query()
            ->where('empresa_id', self::empresaId())
            ->where('tipo_comprobante', $tipo)
            ->where('activa', true)
            ->orderBy('serie')
            ->pluck('serie', 'serie')
            ->all();
    }

    private static function primeraSerieActiva(mixed $tipo): ?string
    {
        return array_key_first(self::seriesActivas($tipo));
    }

    /** @return array<string, string> */
    private static function buscarClientes(mixed $tipo, string $search): array
    {
        $query = Cliente::query()
            ->where('empresa_id', self::empresaId())
            ->where(function (Builder $query) use ($search): void {
                $query->where('numero_documento', 'ilike', "%{$search}%")
                    ->orWhere('razon_social', 'ilike', "%{$search}%");
            });

        if ($tipo === 'FACTURA') {
            $query->where('tipo_documento', '6');
        } elseif ($tipo === 'BOLETA') {
            $query->where('tipo_documento', '!=', '6');
        } else {
            return [];
        }

        return $query
            ->orderBy('razon_social')
            ->limit(20)
            ->get()
            ->mapWithKeys(fn (Cliente $cliente): array => [
                $cliente->id => self::formatearCliente($cliente),
            ])
            ->all();
    }

    private static function etiquetaCliente(?string $clienteId): ?string
    {
        $cliente = self::clienteDelTenant($clienteId);

        return $cliente === null ? null : self::formatearCliente($cliente);
    }

    private static function clienteDelTenant(?string $clienteId): ?Cliente
    {
        if ($clienteId === null || $clienteId === '') {
            return null;
        }

        return Cliente::query()
            ->where('empresa_id', self::empresaId())
            ->find($clienteId);
    }

    private static function formatearCliente(Cliente $cliente): string
    {
        return "{$cliente->razon_social} · {$cliente->numero_documento}";
    }

    private static function tipoDocumentoFormulario(string $tipoDocumento): string
    {
        return match ($tipoDocumento) {
            '0' => 'SIN_DOCUMENTO',
            '1' => 'DNI',
            '4' => 'CARNET_EXTRANJERIA',
            '6' => 'RUC',
            '7' => 'PASAPORTE',
            default => '',
        };
    }

    private static function empresaId(): string
    {
        /** @var Usuario $usuario */
        $usuario = Filament::auth()->user();

        return (string) $usuario->empresa_id;
    }

    private static function resumenTotales(mixed $items): string
    {
        if (! is_array($items)) {
            return 'Total estimado: S/ 0.00';
        }

        $subtotalCentavos = 0;
        $igvCentavos = 0;

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $valorCentavos = (int) round(((float) ($item['valor_unitario'] ?? 0)) * 100);
            $descuentoCentavos = (int) round(((float) ($item['descuento'] ?? 0)) * 100);
            $baseCentavos = (int) round($valorCentavos * (float) ($item['cantidad'] ?? 0)) - $descuentoCentavos;
            $subtotalCentavos += $baseCentavos;
            $igvCentavos += (int) round($baseCentavos * 0.18);
        }

        $subtotal = number_format($subtotalCentavos / 100, 2);
        $igv = number_format($igvCentavos / 100, 2);
        $total = number_format(($subtotalCentavos + $igvCentavos) / 100, 2);

        return "Subtotal: S/ {$subtotal} · IGV: S/ {$igv} · Total estimado: S/ {$total}";
    }
}
