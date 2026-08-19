<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Usuario;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

final class CrearAdministrador extends Command
{
    protected $signature = 'facturacion:crear-admin
        {email : Correo del administrador interno}
        {--name=Administrador : Nombre visible en el panel}';

    protected $description = 'Crea un usuario interno con acceso total al panel administrativo';

    public function handle(): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));
        $name = trim((string) $this->option('name'));
        $password = (string) ($this->secret('Contraseña (mínimo 12 caracteres)') ?? '');
        $confirmation = (string) ($this->secret('Confirma la contraseña') ?? '');

        $validator = Validator::make([
            'email' => $email,
            'name' => $name,
            'password' => $password,
            'password_confirmation' => $confirmation,
        ], [
            'email' => ['required', 'email', 'unique:usuarios,email'],
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Password::min(12)->letters()->mixedCase()->numbers()->symbols()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $usuario = Usuario::query()->create([
            'empresa_id' => null,
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'email_verified_at' => now(),
        ]);

        $rol = Role::findOrCreate('super_admin', 'web');
        $usuario->assignRole($rol);

        $this->components->info("Administrador {$email} creado. Ingresa en /admin.");

        return self::SUCCESS;
    }
}
