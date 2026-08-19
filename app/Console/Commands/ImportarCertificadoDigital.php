<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Facturacion\Application\CasosDeUso\CrearCertificadoDigital;
use Modules\Facturacion\Application\DTO\CrearCertificadoDigitalInput;

final class ImportarCertificadoDigital extends Command
{
    protected $signature = 'facturacion:importar-certificado
        {empresaId : UUID de la empresa}
        {archivo : Ruta al archivo P12/PFX o PEM}
        {--alias=Principal : Nombre interno del certificado}';

    protected $description = 'Importa y valida un certificado digital para una empresa';

    public function __construct(private readonly CrearCertificadoDigital $crearCertificado)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $ruta = (string) $this->argument('archivo');
        $contenido = is_file($ruta) ? file_get_contents($ruta) : false;

        if ($contenido === false) {
            $this->components->error("No se pudo leer el certificado: {$ruta}");

            return self::FAILURE;
        }

        $password = (string) ($this->secret('Contraseña del certificado') ?? '');

        $certificado = $this->crearCertificado->ejecutar(new CrearCertificadoDigitalInput(
            empresaId: (string) $this->argument('empresaId'),
            contenido: $contenido,
            password: $password,
            alias: (string) $this->option('alias'),
        ));

        $this->components->info('Certificado importado y cifrado correctamente.');
        $this->table(['Dato', 'Valor'], [
            ['certificado_id', $certificado->id()],
            ['huella SHA-256', $certificado->huellaSha256()],
            ['vence', $certificado->fechaExpiracion()->format('Y-m-d')],
        ]);

        return self::SUCCESS;
    }
}
