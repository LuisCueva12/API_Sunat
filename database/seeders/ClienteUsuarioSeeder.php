<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Usuario;
use Illuminate\Database\Seeder;
use Modules\Facturacion\Application\CasosDeUso\CrearEmpresa;
use Modules\Facturacion\Application\DTO\CrearEmpresaInput;
use Modules\Facturacion\Domain\Puertos\RepositorioEmpresa;

/**
 * Empresa + usuario de prueba para /app — solo entorno local, nunca producción
 */
final class ClienteUsuarioSeeder extends Seeder
{
    public const RUC = '20100070970';

    public const EMAIL = 'cliente@local.test';

    public const PASSWORD = 'Cliente123!Local';

    public function __construct(
        private readonly RepositorioEmpresa $repositorioEmpresa,
        private readonly CrearEmpresa $crearEmpresa,
    ) {}

    public function run(): void
    {
        if (! app()->environment('local')) {
            $this->command?->warn(self::class.' omitido: solo corre en entorno local.');

            return;
        }

        $empresa = $this->repositorioEmpresa->buscarPorRuc(self::RUC)
            ?? $this->crearEmpresa->ejecutar(new CrearEmpresaInput(
                ruc: self::RUC,
                razonSocial: 'Empresa Cliente Local SAC',
                nombreComercial: 'Cliente Local',
            ));

        Usuario::query()->updateOrCreate(
            ['email' => self::EMAIL],
            [
                'empresa_id' => $empresa->id(),
                'name' => 'Cliente Local',
                'password' => self::PASSWORD,
                'email_verified_at' => now(),
            ],
        );

        $this->command?->info(
            'Usuario /app listo → '.self::EMAIL.' / '.self::PASSWORD.' (empresa: '.self::RUC.')',
        );
    }
}
