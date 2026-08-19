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
        Schema::table('oauth_clients', function (Blueprint $table) {
            $table->jsonb('scopes')->nullable()->after('grant_types');
            $table->timestampTz('ultimo_uso_at')->nullable();
        });

        // Passport crea owner_id como bigint (asume PK autoincremental). Todo
        // este proyecto usa UUID como PK (empresas.id incluido), así que hay
        // que retiparlo antes de poder usar owner_type/owner_id -> Empresa.
        DB::statement('ALTER TABLE oauth_clients ALTER COLUMN owner_id TYPE uuid USING NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE oauth_clients ALTER COLUMN owner_id TYPE bigint USING NULL');

        Schema::table('oauth_clients', function (Blueprint $table) {
            $table->dropColumn(['scopes', 'ultimo_uso_at']);
        });
    }
};
