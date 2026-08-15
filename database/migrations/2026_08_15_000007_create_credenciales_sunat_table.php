<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credenciales_sunat', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('empresa_id')->constrained('empresas');
            $table->string('entorno');
            $table->text('usuario_sol_cifrado');
            $table->text('clave_sol_cifrada');
            $table->string('estado')->default('ACTIVA');
            $table->timestampsTz();

            $table->unique(['empresa_id', 'entorno']);
        });

        DB::statement("ALTER TABLE credenciales_sunat ADD CONSTRAINT credenciales_entorno_check CHECK (entorno IN ('BETA','PRODUCCION'))");
        DB::statement("ALTER TABLE credenciales_sunat ADD CONSTRAINT credenciales_estado_check CHECK (estado IN ('ACTIVA','INACTIVA'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('credenciales_sunat');
    }
};
