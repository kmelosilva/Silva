<?php

namespace App\Services;

use App\Models\Mission;
use App\Models\User;
use App\Support\AgentDay;
use Illuminate\Database\Eloquent\Builder;

/**
 * A sugestão do dia, calculada a cada pedido e sem gravar nada.
 */
class DailySuggestion
{
    public function __construct(private readonly AgentProgress $progress) {}

    public function for(User $user): Suggestion
    {
        // Termine o que começou primeiro: a missão em andamento aceita há mais tempo.
        $inProgress = $this->progress->missionsOf($user)->first(
            fn (MissionProgress $progress) => ! $progress->isCompleted()
                && $progress->nextCoordinate !== null
                && $progress->userMission->mission->retired_at === null
        );

        if ($inProgress) {
            return Suggestion::nextCoordinate($inProgress->userMission->mission, $inProgress->nextCoordinate);
        }

        $day = AgentDay::today();
        $eligible = Mission::query()
            ->inModules()
            ->suggestableFor($user->ensureAgent()->level, $day)
            ->whereDoesntHave('userMissions', fn (Builder $accepted) => $accepted->where('user_id', $user->id))
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        if ($eligible->isEmpty()) {
            return Suggestion::none();
        }

        // A mesma escolha o dia todo para o mesmo agente, sem precisar guardar.
        return Suggestion::mission($eligible[crc32("{$user->id}|{$day}") % $eligible->count()]);
    }
}
