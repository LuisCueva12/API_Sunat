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
        Schema::create('certificados_digitales', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('empresa_id')->constrained('empresas');
            $table->string('alias')->nullable();
            $table->text('contenido_cifrado');
            $table->text('password_cifrado')->nullable();
            $table->char('huella_sha256', 64)->nullable();
            $table->date('fecha_emision')->nullable();
            $table->date('fecha_expiracion');
            $table->string('estado')->default('ACTIVO');
            $table->timestampsTz();

            $table->index(['empresa_id', 'estado']);
        });

        DB::statement("ALTER TABLE certificados_digitales ADD CONSTRAINT certificados_estado_check CHECK (estado IN ('ACTIVO','VENCIDO','REVOCADO','REEMPLAZADO'))");

        DB::statement('CREATE UNIQUE INDEX certificados_un_activo_por_empresa ON certificados_digitales (empresa_id) WHERE estado = \'ACTIVO\'');
    }

    public function down(): void
    {
        Schema::dropIfExists('certificados_digitales');
    }
};
