<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE idempotency_keys DROP CONSTRAINT idempotency_keys_pkey');
        DB::statement('ALTER TABLE idempotency_keys ADD PRIMARY KEY (empresa_id, endpoint, clave)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE idempotency_keys DROP CONSTRAINT idempotency_keys_pkey');
        DB::statement('ALTER TABLE idempotency_keys ADD PRIMARY KEY (empresa_id, clave)');
    }
};
