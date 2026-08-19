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
        Schema::table('comprobantes', function (Blueprint $table) {
            $table->dropForeign(['api_key_id']);
        });

        Schema::table('auditorias', function (Blueprint $table) {
            $table->dropForeign(['api_key_id']);
        });

        Schema::dropIfExists('api_keys');

        Schema::table('comprobantes', function (Blueprint $table) {
            $table->renameColumn('api_key_id', 'oauth_client_id');
        });

        Schema::table('auditorias', function (Blueprint $table) {
            $table->renameColumn('api_key_id', 'oauth_client_id');
        });

        Schema::table('comprobantes', function (Blueprint $table) {
            $table->foreign('oauth_client_id')->references('id')->on('oauth_clients');
        });

        Schema::table('auditorias', function (Blueprint $table) {
            $table->foreign('oauth_client_id')->references('id')->on('oauth_clients');
        });
    }

    public function down(): void
    {
        Schema::table('comprobantes', function (Blueprint $table) {
            $table->dropForeign(['oauth_client_id']);
        });

        Schema::table('auditorias', function (Blueprint $table) {
            $table->dropForeign(['oauth_client_id']);
        });

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

        Schema::table('comprobantes', function (Blueprint $table) {
            $table->renameColumn('oauth_client_id', 'api_key_id');
        });

        Schema::table('auditorias', function (Blueprint $table) {
            $table->renameColumn('oauth_client_id', 'api_key_id');
        });

        Schema::table('comprobantes', function (Blueprint $table) {
            $table->foreign('api_key_id')->references('id')->on('api_keys');
        });

        Schema::table('auditorias', function (Blueprint $table) {
            $table->foreign('api_key_id')->references('id')->on('api_keys');
        });
    }
};
