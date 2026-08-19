<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eventos_comprobante', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('comprobante_id')->constrained('comprobantes');
            $table->foreignUuid('empresa_id')->constrained('empresas');
            $table->string('tipo_evento');
            $table->string('actor')->nullable();
            $table->string('request_id')->nullable();
            $table->jsonb('datos')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['comprobante_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eventos_comprobante');
    }
};
