<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Facturacion\Domain\Comprobante\TipoComprobante;
use Modules\Facturacion\Domain\ValueObjects\Serie as SerieVO;
use Modules\Facturacion\Infrastructure\Persistencia\Eloquent\AsignadorCorrelativoPostgres;

// Concurrencia real (procesos del SO vía pcntl_fork), no llamadas
// secuenciales en el mismo proceso — es lo único que realmente prueba que
// el SELECT ... FOR UPDATE serializa el acceso. Ver docs/01_ARQUITECTURA.md
// Fase 1.
beforeEach(function () {
    $this->empresaId = (string) Str::uuid();
    $this->serieId = (string) Str::uuid();

    DB::table('empresas')->insert([
        'id' => $this->empresaId,
        'ruc' => '20100070970',
        'razon_social' => 'Empresa de prueba de concurrencia',
        'estado' => 'ACTIVA',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('series')->insert([
        'id' => $this->serieId,
        'empresa_id' => $this->empresaId,
        'tipo_comprobante' => 'FACTURA',
        'serie' => 'F001',
        'correlativo_actual' => 0,
        'activa' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // RefreshDatabase deja el test dentro de una transacción sin confirmar;
    // los procesos hijos abren conexiones propias y no verían estas filas
    // hasta que se confirme. Confirmamos y abrimos una nueva para que el
    // rollback final del framework tenga una transacción activa que revertir.
    DB::commit();
    DB::beginTransaction();
});

afterEach(function () {
    DB::table('series')->where('id', $this->serieId)->delete();
    DB::table('empresas')->where('id', $this->empresaId)->delete();
});

it('nunca asigna un correlativo duplicado bajo concurrencia real', function () {
    $numeroProcesos = 8;
    $asignacionesPorProceso = 5;
    $archivoResultados = tempnam(sys_get_temp_dir(), 'correlativos_');

    $empresaId = $this->empresaId;

    // Cerramos la conexión del proceso padre antes de fork(): compartir un
    // mismo socket TCP entre procesos corrompe el protocolo de Postgres.
    DB::disconnect();

    $pids = [];

    for ($p = 0; $p < $numeroProcesos; $p++) {
        $pid = pcntl_fork();

        if ($pid === -1) {
            throw new RuntimeException('No se pudo crear el proceso hijo (pcntl_fork).');
        }

        if ($pid === 0) {
            DB::reconnect();

            $asignador = new AsignadorCorrelativoPostgres;
            $numeros = [];

            for ($i = 0; $i < $asignacionesPorProceso; $i++) {
                $numero = $asignador->asignar($empresaId, TipoComprobante::Factura, new SerieVO('F001'));
                $numeros[] = $numero->correlativo();
            }

            file_put_contents($archivoResultados, implode("\n", $numeros)."\n", FILE_APPEND | LOCK_EX);

            exit(0);
        }

        $pids[] = $pid;
    }

    foreach ($pids as $pid) {
        pcntl_waitpid($pid, $status);
        expect(pcntl_wexitstatus($status))->toBe(0);
    }

    DB::reconnect();

    $lineas = array_values(array_filter(explode("\n", (string) file_get_contents($archivoResultados))));
    $correlativosAsignados = array_map('intval', $lineas);
    unlink($archivoResultados);

    $totalEsperado = $numeroProcesos * $asignacionesPorProceso;

    expect($correlativosAsignados)->toHaveCount($totalEsperado);
    expect(array_unique($correlativosAsignados))->toHaveCount($totalEsperado);
    expect(min($correlativosAsignados))->toBe(1);
    expect(max($correlativosAsignados))->toBe($totalEsperado);

    $serieFinal = DB::table('series')->where('id', $this->serieId)->first();
    expect((int) $serieFinal->correlativo_actual)->toBe($totalEsperado);
})->skip(fn () => ! function_exists('pcntl_fork'), 'Requiere la extensión pcntl.');
