<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conceptos_frecuentes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('empresa_id')->constrained('empresas');
            $table->string('descripcion', 500);
            $table->string('unidad_medida', 3);
            $table->decimal('valor_unitario', 12, 2);
            $table->string('tipo_afectacion_igv', 2)->default('10');
            $table->timestampsTz();

            $table->unique(['empresa_id', 'descripcion']);
            $table->index('empresa_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conceptos_frecuentes');
    }
};
