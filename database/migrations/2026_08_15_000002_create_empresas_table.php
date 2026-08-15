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
        Schema::create('empresas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->char('ruc', 11)->unique();
            $table->string('razon_social');
            $table->string('nombre_comercial')->nullable();
            $table->string('estado')->default('ACTIVA');
            $table->jsonb('configuracion')->nullable();
            $table->timestampsTz();

            $table->index('estado');
        });

        DB::statement("ALTER TABLE empresas ADD CONSTRAINT empresas_estado_check CHECK (estado IN ('ACTIVA','INACTIVA','SUSPENDIDA'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('empresas');
    }
};
