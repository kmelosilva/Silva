<?php

namespace App\Http\Controllers;

use App\Http\Resources\MissionResource;
use App\Models\Mission;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MissionController extends Controller
{
    /**
     * As missões dos módulos, na ordem do catálogo, com as coordenadas publicadas em ordem.
     */
    public function index(): AnonymousResourceCollection
    {
        $missions = Mission::query()
            ->inModules()
            ->with('publishedCoordinates')
            ->orderBy('position')
            ->get();

        return MissionResource::collection($missions);
    }

    /**
     * A missão de entrada, "Seja um agente", com as coordenadas publicadas. Fica fora dos
     * módulos; a tela a abre depois do alistamento e pela sugestão do dia.
     */
    public function entry(): MissionResource
    {
        $mission = Mission::query()->entry()->with('publishedCoordinates')->first();

        abort_if(! $mission, 404, 'Missão não encontrada.');

        return new MissionResource($mission);
    }
}
