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
        Schema::create('entregas_webhook', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('webhook_id')->constrained('webhooks');
            $table->foreignUuid('comprobante_id')->constrained('comprobantes');
            $table->jsonb('payload');
            $table->unsignedSmallInteger('intento')->default(1);
            $table->string('estado')->default('PENDIENTE');
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->text('respuesta_body')->nullable();
            $table->timestampTz('proximo_intento_at')->nullable();
            $table->timestampsTz();

            $table->index(['webhook_id', 'estado']);
        });

        DB::statement("ALTER TABLE entregas_webhook ADD CONSTRAINT entregas_webhook_estado_check CHECK (estado IN ('PENDIENTE','ENTREGADO','FALLIDO','AGOTADO'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('entregas_webhook');
    }
};
