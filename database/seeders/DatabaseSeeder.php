<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Brand di piattaforma (instance_name='Atheneum'), idempotente/non distruttivo.
        $this->call(SettingsSeeder::class);

        // Schola: materie standard (licei/tecnici), idempotente.
        $this->call(SubjectSeeder::class);
    }
}
