<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReflectionRequest;
use App\Http\Resources\ReflectionResource;
use App\Models\Coordinate;
use App\Models\Mission;
use App\Services\Mentoring\Mentoring;
use Illuminate\Http\JsonResponse;

class ReflectionController extends Controller
{
    public function __construct(private readonly Mentoring $mentoring) {}

    /**
     * Guarda a reflexão sobre uma coordenada cumprida e devolve a resposta do submentor:
     * 201 quando é nova, 200 quando já existia (a mesma resposta, sem gerar outra).
     */
    public function store(ReflectionRequest $request, string $mission, string $coordinate): JsonResponse
    {
        $mission = $this->findMission($mission);
        $reflection = $this->mentoring->reflect(
            $request->user(), $mission, $this->findCoordinate($mission, $coordinate), $request->text(),
        );

        // O código fica explícito: o Resource só acerta o 201 quando o modelo acabou de ser criado.
        return (new ReflectionResource($reflection->load('completion.coordinate.mission')))
            ->response()
            ->setStatusCode($reflection->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * Só as missões dos módulos: a de entrada é do Agente Silva, sem reflexão (RN-02).
     */
    private function findMission(string $slug): Mission
    {
        $mission = Mission::query()->inModules()->where('slug', $slug)->first();

        abort_if(! $mission, 404, 'Missão não encontrada.');

        return $mission;
    }

    private function findCoordinate(Mission $mission, string $slug): Coordinate
    {
        $coordinate = $mission->publishedCoordinates()->where('slug', $slug)->first();

        abort_if(! $coordinate, 404, 'Coordenada não encontrada.');

        return $coordinate;
    }
}
