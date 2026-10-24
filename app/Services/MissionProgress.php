<?php

namespace App\Services;

use App\Models\Coordinate;
use App\Models\UserMission;
use App\Support\AgentDay;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Onde o agente está numa missão aceita: as coordenadas publicadas que já cumpriu,
 * em ordem, a próxima (null quando não sobra nenhuma) e a última atividade nela.
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
        public readonly CarbonInterface $lastActivityAt,
    ) {}

    public function isCompleted(): bool
    {
        return $this->userMission->completed_at !== null;
    }

    /**
     * A boas-vindas do submentor (spec mentoria_submentores, RF-06): a missão está em
     * andamento e a última atividade nela foi há pelo menos o prazo da configuração,
     * contado em dias de Brasília.
     */
    public function welcomesBack(): bool
    {
        return ! $this->isCompleted()
            && AgentDay::daysSince($this->lastActivityAt) >= config('mentoring.welcome_back_days');
    }
}
