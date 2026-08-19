<?php

declare(strict_types=1);

namespace App\Filament\Empresa\Resources\Clientes;

use App\Filament\Empresa\Resources\Clientes\Pages\CreateCliente;
use App\Filament\Empresa\Resources\Clientes\Pages\EditCliente;
use App\Filament\Empresa\Resources\Clientes\Pages\ListClientes;
use App\Models\Cliente;
use App\Models\Usuario;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class ClienteResource extends Resource
{
    protected static ?string $model = Cliente::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $modelLabel = 'cliente';

    protected static ?string $pluralModelLabel = 'clientes';

    protected static ?string $recordTitleAttribute = 'razon_social';

    public static function getEloquentQuery(): Builder
    {
        /** @var Usuario $usuario */
        $usuario = Filament::auth()->user();

        return parent::getEloquentQuery()->where('empresa_id', $usuario->empresa_id);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identificación')->schema([
                Select::make('tipo_documento')
                    ->label('Tipo de documento')
                    ->options([
                        '1' => 'DNI',
                        '6' => 'RUC',
                        '4' => 'Carnet de extranjería',
                        '7' => 'Pasaporte',
                        '0' => 'Sin documento',
                    ])
                    ->required()
                    ->disabledOn('edit'),
                TextInput::make('numero_documento')
                    ->label('Número de documento')
                    ->required()
                    ->maxLength(15)
                    ->disabledOn('edit'),
                TextInput::make('razon_social')
                    ->label('Razón social / Nombre')
                    ->required()
                    ->maxLength(255),
                TextInput::make('direccion')
                    ->label('Dirección')
                    ->maxLength(255),
                TextInput::make('email')
                    ->email()
                    ->maxLength(255),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('numero_documento')->label('Documento')->searchable()->sortable(),
                TextColumn::make('razon_social')->label('Razón social / Nombre')->searchable()->sortable(),
                TextColumn::make('email')->toggleable(),
                TextColumn::make('created_at')->label('Registrado')->dateTime()->sortable()->toggleable(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListClientes::route('/'),
            'create' => CreateCliente::route('/create'),
            'edit' => EditCliente::route('/{record}/edit'),
        ];
    }
}
