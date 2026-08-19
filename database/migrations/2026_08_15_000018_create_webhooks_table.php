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
        Schema::create('webhooks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('empresa_id')->constrained('empresas');
            $table->string('url', 500);
            $table->text('secreto_cifrado');
            $table->jsonb('eventos')->default(DB::raw("'[]'::jsonb"));
            $table->string('estado')->default('ACTIVO');
            $table->timestampsTz();
        });

        DB::statement("ALTER TABLE webhooks ADD CONSTRAINT webhooks_estado_check CHECK (estado IN ('ACTIVO','INACTIVO'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('webhooks');
    }
};
