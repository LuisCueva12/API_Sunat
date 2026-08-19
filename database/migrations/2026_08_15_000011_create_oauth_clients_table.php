<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('oauth_clients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // uuid, no bigint (nullableMorphs por defecto) — todo este
            // proyecto usa UUID como PK, owner apunta a Empresa.
            $table->nullableUuidMorphs('owner');
            $table->string('name');
            $table->string('secret')->nullable();
            $table->string('provider')->nullable();
            $table->text('redirect_uris');
            $table->text('grant_types');
            // Columna propia (no estándar de Passport): restringe los scopes
            // que puede solicitar esta integración. Sin ella, Client::hasScope()
            // permite cualquier scope registrado en Passport::tokensCan().
            $table->jsonb('scopes')->nullable();
            $table->boolean('revoked');
            $table->timestamps();
            // Columna propia: Passport no trackea uso, se actualiza en cada
            // request autenticado (ResolverEmpresaIntegracion middleware).
            $table->timestampTz('ultimo_uso_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('oauth_clients');
    }

    /**
     * Get the migration connection name.
     */
    public function getConnection(): ?string
    {
        return $this->connection ?? config('passport.connection');
    }
};
