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
        Schema::create('envios_sunat', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('comprobante_id')->constrained('comprobantes');
            $table->unsignedSmallInteger('intento');
            $table->string('entorno');

            $table->string('codigo_respuesta_sunat')->nullable();
            $table->text('descripcion_respuesta_sunat')->nullable();
            $table->jsonb('notas_sunat')->nullable();
            $table->string('ticket_sunat')->nullable();

            $table->string('xml_path')->nullable();
            $table->string('cdr_path')->nullable();

            $table->unsignedInteger('duracion_ms')->nullable();
            $table->text('error_tecnico')->nullable();

            $table->timestampTz('created_at')->useCurrent();

            $table->unique(['comprobante_id', 'intento']);
        });

        DB::statement("ALTER TABLE envios_sunat ADD CONSTRAINT envios_sunat_entorno_check CHECK (entorno IN ('BETA','PRODUCCION'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('envios_sunat');
    }
};
