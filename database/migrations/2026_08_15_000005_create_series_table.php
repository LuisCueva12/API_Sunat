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
        Schema::create('series', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('empresa_id')->constrained('empresas');
            $table->foreignUuid('establecimiento_id')->nullable()->constrained('establecimientos');
            $table->string('tipo_comprobante');
            $table->string('serie', 4);
            $table->unsignedBigInteger('correlativo_actual')->default(0);
            $table->boolean('activa')->default(true);
            $table->timestampsTz();

            $table->unique(['empresa_id', 'tipo_comprobante', 'serie']);
        });

        DB::statement("ALTER TABLE series ADD CONSTRAINT series_tipo_check CHECK (tipo_comprobante IN ('FACTURA','BOLETA','NOTA_CREDITO','NOTA_DEBITO'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('series');
    }
};
