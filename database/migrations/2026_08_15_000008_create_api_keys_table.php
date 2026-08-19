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
        Schema::create('api_keys', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('empresa_id')->constrained('empresas');
            $table->string('nombre');
            $table->string('prefijo', 16);
            $table->string('hash')->unique();
            $table->jsonb('scopes')->default(DB::raw("'[]'::jsonb"));
            $table->timestampTz('ultimo_uso_at')->nullable();
            $table->timestampTz('expira_at')->nullable();
            $table->string('estado')->default('ACTIVA');
            $table->timestampTz('revocada_at')->nullable();
            $table->timestampsTz();

            $table->index('prefijo');
            $table->index(['empresa_id', 'estado']);
        });

        DB::statement("ALTER TABLE api_keys ADD CONSTRAINT api_keys_estado_check CHECK (estado IN ('ACTIVA','REVOCADA'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('api_keys');
    }
};
