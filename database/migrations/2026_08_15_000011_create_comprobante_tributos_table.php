<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Agregado por tipo de tributo a nivel de comprobante (no por línea —
        // eso ya está en comprobante_items.monto_igv). Permite sumar ICBPER u
        // otros tributos simples sin cambiar el esquema.
        Schema::create('comprobante_tributos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('comprobante_id')->constrained('comprobantes');
            $table->string('tipo_tributo');
            $table->string('codigo')->nullable();
            $table->decimal('base_imponible', 12, 2)->default(0);
            $table->decimal('monto', 12, 2);
            $table->timestampsTz();

            $table->unique(['comprobante_id', 'tipo_tributo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comprobante_tributos');
    }
};
