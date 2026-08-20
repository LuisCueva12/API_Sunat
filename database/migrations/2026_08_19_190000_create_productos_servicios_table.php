<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productos_servicios', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('empresa_id')->constrained('empresas');
            $table->string('codigo', 50)->nullable();
            $table->string('nombre');
            $table->string('tipo', 10);
            $table->string('unidad_medida', 3)->default('NIU');
            $table->decimal('valor_unitario', 12, 2);
            $table->boolean('activo')->default(true);
            $table->timestampsTz();

            $table->index(['empresa_id', 'activo']);
            $table->unique(['empresa_id', 'codigo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('productos_servicios');
    }
};
