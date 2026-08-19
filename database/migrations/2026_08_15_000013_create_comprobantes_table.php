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
        Schema::create('comprobantes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('empresa_id')->constrained('empresas');

            $table->string('tipo');
            $table->string('serie', 4);
            $table->unsignedBigInteger('correlativo');
            $table->string('estado')->default('REGISTRADO');

            $table->char('moneda', 3)->default('PEN');
            $table->decimal('tipo_cambio', 10, 3)->nullable();

            $table->string('receptor_tipo_documento', 1)->nullable();
            $table->string('receptor_numero_documento', 15)->nullable();
            $table->string('receptor_razon_social')->nullable();
            $table->string('receptor_direccion')->nullable();
            $table->string('receptor_email')->nullable();

            $table->date('fecha_emision');
            $table->date('fecha_vencimiento')->nullable();
            $table->string('forma_pago')->default('CONTADO');

            $table->decimal('op_gravada', 12, 2)->default(0);
            $table->decimal('op_exonerada', 12, 2)->default(0);
            $table->decimal('op_inafecta', 12, 2)->default(0);
            $table->decimal('op_gratuita', 12, 2)->default(0);
            $table->decimal('total_igv', 12, 2)->default(0);
            $table->decimal('total_descuentos', 12, 2)->default(0);
            $table->decimal('total', 12, 2);

            $table->uuid('comprobante_referencia_id')->nullable();
            $table->string('tipo_nota')->nullable();
            $table->string('motivo_nota')->nullable();

            $table->jsonb('snapshot_emisor');

            $table->string('idempotency_key')->nullable();
            $table->char('xml_sha256', 64)->nullable();
            $table->char('cdr_sha256', 64)->nullable();

            $table->unsignedSmallInteger('intentos_envio')->default(0);
            $table->text('ultimo_error')->nullable();

            $table->foreignUuid('oauth_client_id')->nullable()->constrained('oauth_clients');
            $table->foreignUuid('creado_por')->nullable()->constrained('usuarios');

            $table->timestampsTz();

            $table->unique(['empresa_id', 'tipo', 'serie', 'correlativo']);
            $table->index(['empresa_id', 'estado']);
            $table->index(['empresa_id', 'fecha_emision']);
        });

        Schema::table('comprobantes', function (Blueprint $table) {
            $table->foreign('comprobante_referencia_id')->references('id')->on('comprobantes');
        });

        DB::statement("ALTER TABLE comprobantes ADD CONSTRAINT comprobantes_tipo_check CHECK (tipo IN ('FACTURA','BOLETA','NOTA_CREDITO','NOTA_DEBITO'))");
        DB::statement("ALTER TABLE comprobantes ADD CONSTRAINT comprobantes_estado_check CHECK (estado IN ('REGISTRADO','PROCESANDO','ACEPTADO','ACEPTADO_CON_OBSERVACIONES','RECHAZADO','ERROR'))");
        DB::statement('ALTER TABLE comprobantes ADD CONSTRAINT comprobantes_total_no_negativo CHECK (total >= 0)');

        // Empresa + idempotency_key es la clave real de dedupe (ver idempotency_keys
        // para el mecanismo completo); este índice parcial es una segunda barrera
        // a nivel del propio comprobante.
        DB::statement('CREATE UNIQUE INDEX comprobantes_idempotency_unico ON comprobantes (empresa_id, idempotency_key) WHERE idempotency_key IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('comprobantes');
    }
};
