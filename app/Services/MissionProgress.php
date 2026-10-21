<?php

namespace App\Services;

use App\Models\Coordinate;
use App\Models\UserMission;
use Illuminate\Support\Collection;

/**
 * Onde o agente está numa missão aceita: as coordenadas publicadas que já cumpriu,
 * em ordem, e a próxima (null quando não sobra nenhuma).
 */
final class MissionProgress
{
    /**
     * @param  Collection<int, Coordinate>  $completedCoordinates
     */
    public function __construct(
        public readonly UserMission $userMission,
        public readonly Collection $completedCoordinates,
        public readonly ?Coordinate $nextCoordinate,
    ) {}

    public function isCompleted(): bool
    {
        return $this->userMission->completed_at !== null;
    }
}
