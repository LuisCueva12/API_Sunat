<?php

declare(strict_types=1);

namespace App\Filament\Empresa\Resources\Comprobantes\Pages;

use App\Filament\Empresa\Resources\Comprobantes\ComprobanteResource;
use App\Models\Comprobante as ComprobanteEloquent;
use App\Models\Usuario;
use DomainException;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Modules\Facturacion\Application\CasosDeUso\EmitirBoleta;
use Modules\Facturacion\Application\CasosDeUso\EmitirFactura;
use Modules\Facturacion\Application\DTO\EmitirComprobanteInput;
use Modules\Facturacion\Application\DTO\ItemInput;

final class CreateComprobante extends CreateRecord
{
    protected static string $resource = ComprobanteResource::class;

    protected static ?string $title = 'Emitir comprobante';

    protected static ?string $breadcrumb = 'Emitir';

    protected static bool $canCreateAnother = false;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        /** @var Usuario $usuario */
        $usuario = Filament::auth()->user();

        try {
            $casoDeUso = match ($data['tipo']) {
                'FACTURA' => app(EmitirFactura::class),
                'BOLETA' => app(EmitirBoleta::class),
                default => throw new InvalidArgumentException('El tipo de comprobante no es válido.'),
            };

            $comprobante = $casoDeUso->ejecutar(new EmitirComprobanteInput(
                empresaId: (string) $usuario->empresa_id,
                serie: (string) $data['serie'],
                receptorTipoDocumento: $this->tipoDocumentoDominio((string) $data['receptor_tipo_documento']),
                receptorNumeroDocumento: (string) ($data['receptor_numero_documento'] ?? ''),
                receptorRazonSocial: (string) $data['receptor_razon_social'],
                items: array_map(
                    fn (array $item): ItemInput => new ItemInput(
                        descripcion: (string) $item['descripcion'],
                        unidadMedida: (string) $item['unidad_medida'],
                        cantidad: (float) $item['cantidad'],
                        valorUnitario: (string) $item['valor_unitario'],
                        tipoAfectacionIgv: (string) $item['tipo_afectacion_igv'],
                        descuento: filled($item['descuento'] ?? null) ? (string) $item['descuento'] : null,
                    ),
                    $data['items'],
                ),
                moneda: 'PEN',
                requestId: request()->attributes->getString('request_id') ?: null,
            ));
        } catch (InvalidArgumentException|DomainException $e) {
            Notification::make()
                ->title('No se pudo emitir el comprobante')
                ->body($e->getMessage())
                ->danger()
                ->send();

            throw new Halt;
        }

        return ComprobanteEloquent::query()->findOrFail($comprobante->id());
    }

    protected function getCreatedNotificationTitle(): string
    {
        return 'Comprobante emitido y enviado a procesar';
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()
            ->label('Emitir comprobante')
            ->icon(Heroicon::OutlinedPaperAirplane);
    }

    protected function getRedirectUrl(): string
    {
        return ComprobanteResource::getUrl('view', ['record' => $this->getRecord()]);
    }

    private function tipoDocumentoDominio(string $tipoDocumento): string
    {
        return match ($tipoDocumento) {
            'SIN_DOCUMENTO' => '0',
            'DNI' => '1',
            'CARNET_EXTRANJERIA' => '4',
            'RUC' => '6',
            'PASAPORTE' => '7',
            default => throw new InvalidArgumentException('El tipo de documento del receptor no es válido.'),
        };
    }
}
