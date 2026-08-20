<?php

declare(strict_types=1);

namespace App\Filament\Empresa\Resources\Webhooks;

use App\Filament\Empresa\Resources\Webhooks\Pages\CreateWebhook;
use App\Filament\Empresa\Resources\Webhooks\Pages\EditWebhook;
use App\Filament\Empresa\Resources\Webhooks\Pages\ListWebhooks;
use App\Models\Usuario;
use App\Models\Webhook;
use App\Services\Webhooks\ValidadorUrlWebhook;
use BackedEnum;
use Closure;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

final class WebhookResource extends Resource
{
    protected static ?string $model = Webhook::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSignal;

    protected static ?string $modelLabel = 'webhook';

    protected static ?string $pluralModelLabel = 'webhooks';

    public static function getEloquentQuery(): Builder
    {
        /** @var Usuario $usuario */
        $usuario = Filament::auth()->user();

        return parent::getEloquentQuery()->where('empresa_id', $usuario->empresa_id);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Entrega segura')->schema([
                TextInput::make('url')
                    ->label('URL HTTPS')
                    ->url()
                    ->required()
                    ->maxLength(500)
                    ->rule(static function (): Closure {
                        return static function (string $attribute, mixed $value, Closure $fail): void {
                            try {
                                app(ValidadorUrlWebhook::class)->validar((string) $value);
                            } catch (Throwable $e) {
                                $fail($e->getMessage());
                            }
                        };
                    }),
                TextInput::make('secreto_cifrado')
                    ->label('Secreto de firma')
                    ->password()
                    ->revealable(false)
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->minLength(32)
                    ->maxLength(255)
                    ->afterStateHydrated(fn (TextInput $component): TextInput => $component->state(null))
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->helperText('Guárdalo en tu integración. En edición, déjalo vacío para conservarlo o escribe uno nuevo para rotarlo.'),
                CheckboxList::make('eventos')
                    ->options([
                        'comprobante.aceptado' => 'Aceptado',
                        'comprobante.aceptado_con_observaciones' => 'Aceptado con observaciones',
                        'comprobante.rechazado' => 'Rechazado',
                        'comprobante.error' => 'Error de procesamiento',
                    ])
                    ->required()
                    ->columns(1),
                Select::make('estado')
                    ->options(['ACTIVO' => 'Activo', 'INACTIVO' => 'Inactivo'])
                    ->default('ACTIVO')
                    ->required(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('url')->label('Destino')->searchable()->wrap(),
                TextColumn::make('estado')->badge(),
                TextColumn::make('updated_at')->label('Actualizado')->dateTime()->sortable(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWebhooks::route('/'),
            'create' => CreateWebhook::route('/create'),
            'edit' => EditWebhook::route('/{record}/edit'),
        ];
    }
}
