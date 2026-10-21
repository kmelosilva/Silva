<?php

namespace App\Services;

use App\Exceptions\ProgressRefused;
use App\Models\Agent;
use App\Models\Coordinate;
use App\Models\CoordinateCompletion;
use App\Models\Mission;
use App\Models\User;
use App\Models\UserMission;
use App\Support\AgentDay;
use App\Support\LevelCurve;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * O ciclo do agente: aceitar a missão, cumprir as coordenadas em ordem, ganhar XP,
 * subir de nível e contar o dia na sequência. É o único código que escreve no XP,
 * no nível e na sequência do agente.
 */
class AgentProgress
{
    /**
     * O progresso do agente em cada missão aceita, na ordem de aceite.
     *
     * @return Collection<int, MissionProgress>
     */
    public function missionsOf(User $user): Collection
    {
        $completedIds = $this->completedCoordinateIds($user);

        return $user->missions()
            ->with('mission.publishedCoordinates')
            ->orderBy('accepted_at')
            ->orderBy('id')
            ->get()
            ->map(fn (UserMission $userMission) => $this->progressFor(
                $userMission,
                $userMission->mission->publishedCoordinates,
                $completedIds,
            ));
    }

    /**
     * @throws ProgressRefused se a missão já foi aceita (em andamento ou concluída)
     */
    public function accept(User $user, Mission $mission): MissionProgress
    {
        $userMission = UserMission::createOrFirst(
            ['user_id' => $user->id, 'mission_id' => $mission->id],
            ['accepted_at' => now()],
        );

        if (! $userMission->wasRecentlyCreated) {
            throw $userMission->completed_at ? ProgressRefused::alreadyCompleted() : ProgressRefused::alreadyAccepted();
        }

        $userMission->setRelation('mission', $mission);

        return $this->progressFor($userMission, $mission->publishedCoordinates()->get(), []);
    }

    /**
     * Cumpre a coordenada. Repetir uma coordenada já cumprida responde com 0 XP, sem erro.
     *
     * @throws ProgressRefused se a missão não foi aceita ou se a coordenada não é a próxima
     */
    public function complete(User $user, Mission $mission, Coordinate $coordinate): CompletionResult
    {
        $agentId = $user->ensureAgent()->id;

        return DB::transaction(function () use ($user, $mission, $coordinate, $agentId) {
            // Com o agente travado, dois envios ao mesmo tempo ficam em fila.
            $agent = Agent::whereKey($agentId)->lockForUpdate()->firstOrFail();

            $userMission = UserMission::where('user_id', $user->id)
                ->where('mission_id', $mission->id)
                ->first();

            if (! $userMission) {
                throw ProgressRefused::notAccepted();
            }

            $userMission->setRelation('mission', $mission);

            $published = $mission->publishedCoordinates()->get();
            $completedIds = $this->completedCoordinateIds($user);

            if (in_array($coordinate->id, $completedIds, true)) {
                return new CompletionResult(
                    $this->progressFor($userMission, $published, $completedIds), $agent, 0, 0, false, true,
                );
            }

            $progress = $this->progressFor($userMission, $published, $completedIds);

            if (! $progress->nextCoordinate?->is($coordinate)) {
                $number = $published->search(fn (Coordinate $item) => $item->is($progress->nextCoordinate)) + 1;

                throw ProgressRefused::outOfOrder($progress->nextCoordinate, $number);
            }

            CoordinateCompletion::create([
                'user_id' => $user->id,
                'coordinate_id' => $coordinate->id,
                'xp' => $coordinate->xp,
                'completed_at' => now(),
            ]);
            $completedIds[] = $coordinate->id;

            $levelBefore = $agent->level;
            $agent->xp += $coordinate->xp;
            $agent->level = max($agent->level, LevelCurve::levelFor($agent->xp));
            $this->countActiveDay($agent);
            $agent->save();

            $progress = $this->progressFor($userMission, $published, $completedIds);
            $missionCompleted = $progress->nextCoordinate === null && $userMission->completed_at === null;

            if ($missionCompleted) {
                $userMission->update(['completed_at' => now()]);
            }

            return new CompletionResult(
                $progress, $agent, $coordinate->xp, $agent->level - $levelBefore, $missionCompleted, false,
            );
        });
    }

    /**
     * Conta o dia de hoje (Brasília) na sequência: ontem ativo soma 1, outro dia recomeça em 1.
     */
    private function countActiveDay(Agent $agent): void
    {
        $today = AgentDay::today();

        if ($agent->last_active_on === $today) {
            return;
        }

        $agent->streak_current = $agent->last_active_on === AgentDay::yesterday() ? $agent->streak_current + 1 : 1;
        $agent->streak_best = max($agent->streak_best, $agent->streak_current);
        $agent->last_active_on = $today;
    }

    /**
     * @param  Collection<int, Coordinate>  $published  as coordenadas publicadas da missão, em ordem
     * @param  array<int, int>  $completedIds
     */
    private function progressFor(UserMission $userMission, Collection $published, array $completedIds): MissionProgress
    {
        [$completed, $pending] = $published->partition(
            fn (Coordinate $coordinate) => in_array($coordinate->id, $completedIds, true)
        );

        return new MissionProgress($userMission, $completed->values(), $pending->first());
    }

    /**
     * @return array<int, int>
     */
    private function completedCoordinateIds(User $user): array
    {
        return $user->coordinateCompletions()
            ->pluck('coordinate_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
