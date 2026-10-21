<?php

namespace App\Http\Resources;

use App\Services\CompletionResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A resposta de cumprir uma coordenada: progresso, painel e o que aconteceu.
 * Espelhado em silva-frontend/src/types/agent.ts.
 *
 * @property CompletionResult $resource
 */
class CompletionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $result = $this->resource;

        return [
            'progress' => new MissionProgressResource($result->progress),
            'agent' => new AgentResource($result->agent),
            'result' => [
                'xp_gained' => $result->xpGained,
                'levels_gained' => $result->levelsGained,
                'mission_completed' => $result->missionCompleted,
                'already_completed' => $result->alreadyCompleted,
            ],
        ];
    }
}
