<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

// Sem WithoutModelEvents: a conta de teste precisa do evento que cria o agente.
class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            MissionCatalogSeeder::class,
        ]);

        // Condicional para o seed poder rodar mais de uma vez.
        if (! User::where('email', 'test@example.com')->exists()) {
            User::factory()->create([
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);
        }
    }
}
