<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        if (! app()->environment('local')) {
            $this->command?->warn(
                'Seeders de usuarios de prueba omitidos: solo corren en entorno local.',
            );

            return;
        }

        $this->call([
            AdminUsuarioSeeder::class,
            ClienteUsuarioSeeder::class,
        ]);
    }
}
