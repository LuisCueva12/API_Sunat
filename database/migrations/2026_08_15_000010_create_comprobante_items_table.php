<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comprobante_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('comprobante_id')->constrained('comprobantes');
            $table->unsignedSmallInteger('numero_orden');

            $table->string('codigo_producto')->nullable();
            $table->string('descripcion', 500);
            $table->string('unidad_medida', 3)->default('NIU');

            $table->decimal('cantidad', 12, 3);
            $table->decimal('valor_unitario', 12, 3);
            $table->decimal('precio_unitario', 12, 3);

            $table->string('tipo_afectacion_igv', 2);
            $table->decimal('monto_igv', 12, 2)->default(0);
            $table->decimal('monto_valor_venta', 12, 2);
            $table->decimal('descuento', 12, 2)->default(0);

            $table->timestampsTz();

            $table->unique(['comprobante_id', 'numero_orden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comprobante_items');
    }
};
