<?php

namespace App\Http\Controllers;

use App\Http\Resources\CompletionResource;
use App\Http\Resources\MissionProgressResource;
use App\Models\Coordinate;
use App\Models\Mission;
use App\Services\AgentProgress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MissionProgressController extends Controller
{
    public function __construct(private readonly AgentProgress $progress) {}

    /**
     * O progresso do agente em cada missão aceita.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        return MissionProgressResource::collection($this->progress->missionsOf($request->user()));
    }

    public function accept(Request $request, string $mission): JsonResponse
    {
        $progress = $this->progress->accept($request->user(), $this->findMission($mission));

        return (new MissionProgressResource($progress))->response()->setStatusCode(201);
    }

    public function complete(Request $request, string $mission, string $coordinate): CompletionResource
    {
        $mission = $this->findMission($mission, withEntry: true);

        return new CompletionResource(
            $this->progress->complete($request->user(), $mission, $this->findCoordinate($mission, $coordinate))
        );
    }

    /**
     * Aceitar procura só nas missões dos módulos: a de entrada chega pelo alistamento.
     * Cumprir procura também na de entrada. Aposentada ou inexistente dá 404.
     */
    private function findMission(string $slug, bool $withEntry = false): Mission
    {
        $missions = $withEntry ? Mission::query()->playable() : Mission::query()->inModules();
        $mission = $missions->where('slug', $slug)->first();

        abort_if(! $mission, 404, 'Missão não encontrada.');

        return $mission;
    }

    /**
     * Só as coordenadas publicadas daquela missão.
     */
    private function findCoordinate(Mission $mission, string $slug): Coordinate
    {
        $coordinate = $mission->publishedCoordinates()->where('slug', $slug)->first();

        abort_if(! $coordinate, 404, 'Coordenada não encontrada.');

        return $coordinate;
    }
}
