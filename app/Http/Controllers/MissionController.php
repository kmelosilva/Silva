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
}
