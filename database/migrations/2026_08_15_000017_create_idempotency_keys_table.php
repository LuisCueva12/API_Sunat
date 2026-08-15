<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->foreignUuid('empresa_id')->constrained('empresas');
            $table->string('clave');
            $table->string('endpoint');
            $table->char('hash_solicitud', 64);
            $table->foreignUuid('comprobante_id')->nullable()->constrained('comprobantes');
            $table->string('estado')->default('PROCESANDO');
            $table->jsonb('respuesta_cache')->nullable();
            $table->timestampTz('expira_at');
            $table->timestampTz('created_at')->useCurrent();

            $table->primary(['empresa_id', 'clave']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
