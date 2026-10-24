<?php

namespace App\Http\Resources;

use App\Services\MissionProgress;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * O progresso do agente numa missão aceita. Espelhado em silva-frontend/src/types/agent.ts.
 *
 * @property MissionProgress $resource
 */
class MissionProgressResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $progress = $this->resource;
        $userMission = $progress->userMission;

        return [
            'mission_slug' => $userMission->mission->slug,
            'status' => $progress->isCompleted() ? 'completed' : 'in_progress',
            'completed_coordinates' => $progress->completedCoordinates->pluck('slug')->all(),
            'next_coordinate' => $progress->nextCoordinate?->slug,
            'accepted_at' => $userMission->accepted_at?->toJSON(),
            'completed_at' => $userMission->completed_at?->toJSON(),
            'welcome_back' => $progress->welcomesBack(),
        ];
    }
}
