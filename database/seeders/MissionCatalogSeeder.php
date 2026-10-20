<?php

namespace Database\Seeders;

use App\Services\MissionCatalogSync;
use Illuminate\Database\Seeder;

class MissionCatalogSeeder extends Seeder
{
    /**
     * Grava o catálogo de missões (database/data/mission_catalog.php).
     * Pode rodar quantas vezes for preciso: não duplica nem apaga progresso.
     */
    public function run(MissionCatalogSync $sync): void
    {
        $result = $sync->sync(require database_path('data/mission_catalog.php'));

        $this->command?->info(sprintf(
            'Catálogo: %d missões e %d coordenadas no catálogo; %d missões e %d coordenadas aposentadas agora.',
            $result['missions'],
            $result['coordinates'],
            $result['retired_missions'],
            $result['retired_coordinates'],
        ));
    }
}
