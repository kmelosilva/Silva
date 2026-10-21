<?php

namespace App\Services;

use App\Enums\Category;
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
        $open = $this->progress->missionsOf($user)->filter(
            fn (MissionProgress $progress) => ! $progress->isCompleted()
                && $progress->nextCoordinate !== null
                && $progress->userMission->mission->retired_at === null
        );

        // A missão de entrada vem antes de tudo; depois, termine o que começou primeiro:
        // a missão em andamento aceita há mais tempo.
        $inProgress = $open->first(fn (MissionProgress $progress) => $progress->userMission->mission->is_entry)
            ?? $open->first();

        if ($inProgress) {
            return Suggestion::nextCoordinate($inProgress->userMission->mission, $inProgress->nextCoordinate);
        }

        $agent = $user->ensureAgent();
        $day = AgentDay::today();
        $eligible = Mission::query()
            ->inModules()
            ->suggestableFor($agent->level, $day)
            ->whereDoesntHave('userMissions', fn (Builder $accepted) => $accepted->where('user_id', $user->id))
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        // Primeiro as áreas de prioridade; se nenhuma tiver missão, qualquer área.
        $priorities = $agent->priorityAreas();
        $unavailable = $priorities
            ->reject(fn (Category $area) => $eligible->contains(fn (Mission $mission) => $mission->category === $area))
            ->values()
            ->all();
        $preferred = $eligible->filter(fn (Mission $mission) => $priorities->contains($mission->category))->values();
        $pool = $preferred->isNotEmpty() ? $preferred : $eligible;

        if ($pool->isEmpty()) {
            return Suggestion::none($unavailable);
        }

        // A mesma escolha o dia todo para o mesmo agente, sem precisar guardar.
        return Suggestion::mission($pool[crc32("{$user->id}|{$day}") % $pool->count()], $unavailable);
    }
}
