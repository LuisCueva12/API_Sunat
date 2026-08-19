<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ApiKey as ApiKeyEloquent;
use App\Services\Certificados\GeneradorCertificadoAutofirmado;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Facturacion\Application\CasosDeUso\CrearApiKey;
use Modules\Facturacion\Application\CasosDeUso\CrearCertificadoDigital;
use Modules\Facturacion\Application\CasosDeUso\CrearCredencialSunat;
use Modules\Facturacion\Application\CasosDeUso\CrearEmpresa;
use Modules\Facturacion\Application\CasosDeUso\CrearSerie;
use Modules\Facturacion\Application\DTO\CrearApiKeyInput;
use Modules\Facturacion\Application\DTO\CrearCertificadoDigitalInput;
use Modules\Facturacion\Application\DTO\CrearCredencialSunatInput;
use Modules\Facturacion\Application\DTO\CrearEmpresaInput;
use Modules\Facturacion\Application\DTO\CrearSerieInput;
use Modules\Facturacion\Domain\Comprobante\TipoComprobante;
use Modules\Facturacion\Domain\Puertos\RepositorioCertificado;
use Modules\Facturacion\Domain\Puertos\RepositorioEmpresa;
use Modules\Facturacion\Domain\Puertos\RepositorioSerie;
use Modules\Facturacion\Domain\ValueObjects\Serie;

final class PrepararEntornoBeta extends Command
{
    protected $signature = 'facturacion:preparar-beta
        {--ruc=20100066603 : RUC válido usado como emisor de pruebas}
        {--nueva-api-key : Generar otra API Key aunque ya exista una para BETA}';

    protected $description = 'Configura una empresa local para probar el envío de facturas contra SUNAT BETA';

    public function __construct(
        private readonly CrearEmpresa $crearEmpresa,
        private readonly CrearSerie $crearSerie,
        private readonly CrearCertificadoDigital $crearCertificado,
        private readonly CrearCredencialSunat $crearCredencial,
        private readonly CrearApiKey $crearApiKey,
        private readonly RepositorioEmpresa $repositorioEmpresa,
        private readonly RepositorioSerie $repositorioSerie,
        private readonly RepositorioCertificado $repositorioCertificado,
        private readonly GeneradorCertificadoAutofirmado $generadorCertificado,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->components->error('Este comando usa credenciales públicas de prueba y está bloqueado en producción.');

            return self::FAILURE;
        }

        if (strtolower((string) config('facturacion.sunat.entorno_por_defecto')) !== 'beta') {
            $this->components->error('Configura SUNAT_ENTORNO=beta antes de preparar o emitir comprobantes de prueba.');

            return self::FAILURE;
        }

        $ruc = (string) $this->option('ruc');
        $empresa = $this->repositorioEmpresa->buscarPorRuc($ruc)
            ?? $this->crearEmpresa->ejecutar(new CrearEmpresaInput(
                ruc: $ruc,
                razonSocial: 'Empresa de Pruebas SUNAT BETA',
                nombreComercial: 'BETA',
            ));

        $serie = new Serie('F001');

        if (! $this->repositorioSerie->existe($empresa->id(), TipoComprobante::Factura, $serie)) {
            $this->crearSerie->ejecutar(new CrearSerieInput(
                empresaId: $empresa->id(),
                tipoComprobante: TipoComprobante::Factura->value,
                serie: $serie->valor(),
            ));
        }

        DB::table('establecimientos')->upsert(
            [[
                'id' => (string) Str::uuid7(),
                'empresa_id' => $empresa->id(),
                'codigo' => '0000',
                'denominacion' => 'Domicilio fiscal BETA',
                'ubigeo' => '150101',
                'departamento' => 'LIMA',
                'provincia' => 'LIMA',
                'distrito' => 'LIMA',
                'direccion' => 'AV. PRUEBA 123',
                'es_principal' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]],
            ['empresa_id', 'codigo'],
            [
                'denominacion',
                'ubigeo',
                'departamento',
                'provincia',
                'distrito',
                'direccion',
                'es_principal',
                'updated_at',
            ],
        );

        $certificado = $this->repositorioCertificado->buscarActivoPorEmpresa($empresa->id());

        if ($certificado === null || ! $certificado->estaVigente()) {
            $this->crearCertificado->ejecutar(new CrearCertificadoDigitalInput(
                empresaId: $empresa->id(),
                contenido: $this->generadorCertificado->generar($ruc),
                password: '',
                alias: 'Autofirmado SUNAT BETA',
            ));
        }

        $this->crearCredencial->ejecutar(new CrearCredencialSunatInput(
            empresaId: $empresa->id(),
            entorno: 'BETA',
            usuarioSol: $ruc.'MODDATOS',
            claveSol: 'moddatos',
        ));

        $apiKeyExistente = ApiKeyEloquent::query()
            ->where('empresa_id', $empresa->id())
            ->where('nombre', 'Integración SUNAT BETA')
            ->where('estado', 'ACTIVA')
            ->exists();

        $apiKey = null;

        if (! $apiKeyExistente || $this->option('nueva-api-key')) {
            $apiKey = $this->crearApiKey->ejecutar(new CrearApiKeyInput(
                empresaId: $empresa->id(),
                nombre: 'Integración SUNAT BETA',
                scopes: ['comprobantes:crear', 'comprobantes:leer', 'comprobantes:reintentar'],
            ))->claveCompleta;
        }

        $this->components->info('Entorno SUNAT BETA preparado.');
        $this->table(['Dato', 'Valor'], [
            ['empresa_id', $empresa->id()],
            ['RUC', $ruc],
            ['Serie factura', 'F001'],
            ['Usuario BETA', $ruc.'MODDATOS'],
            ['API Key nueva', $apiKey ?? 'Ya existía; usa --nueva-api-key si necesitas rotarla'],
        ]);

        return self::SUCCESS;
    }
}
