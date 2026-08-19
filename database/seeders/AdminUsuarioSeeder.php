<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Usuario;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

/**
 * Usuario interno de prueba para /admin — solo entorno local, nunca producción
 */
final class AdminUsuarioSeeder extends Seeder
{
    public const EMAIL = 'admin@local.test';

    public const PASSWORD = 'Admin123!Local';

    public function run(): void
    {
        if (! app()->environment('local')) {
            $this->command?->warn(self::class.' omitido: solo corre en entorno local.');

            return;
        }

        $usuario = Usuario::query()->updateOrCreate(
            ['email' => self::EMAIL],
            [
                'empresa_id' => null,
                'name' => 'Admin Local',
                'password' => self::PASSWORD,
                'email_verified_at' => now(),
            ],
        );

        $usuario->syncRoles([Role::findOrCreate('super_admin', 'web')]);

        $this->command?->info(
            'Usuario /admin listo → '.self::EMAIL.' / '.self::PASSWORD,
        );
    }
}
