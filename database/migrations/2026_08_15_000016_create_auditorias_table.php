<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auditorias', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('empresa_id')->nullable()->constrained('empresas');
            $table->foreignUuid('usuario_id')->nullable()->constrained('usuarios');
            $table->foreignUuid('api_key_id')->nullable()->constrained('api_keys');
            $table->string('accion');
            $table->string('entidad_tipo')->nullable();
            $table->string('entidad_id')->nullable();
            $table->ipAddress('ip')->nullable();
            $table->string('request_id')->nullable();
            $table->jsonb('datos_previos')->nullable();
            $table->jsonb('datos_nuevos')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['empresa_id', 'created_at']);
            $table->index(['entidad_tipo', 'entidad_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auditorias');
    }
};
