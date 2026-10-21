<?php

namespace App\Services;

use App\Enums\Category;
use App\Enums\EnlistmentStep;
use App\Exceptions\EnlistmentRefused;
use App\Models\Agent;
use App\Models\Mission;
use App\Models\User;
use App\Support\Codename;
use Illuminate\Support\Facades\DB;

/**
 * O alistamento: juramento, codinome e prioridade, uma vez por agente. Concluir põe a
 * missão de entrada em andamento pelo AgentProgress, o mesmo caminho do botão "Aceitar".
 */
class Enlistment
{
    public const MAX_PRIORITIES = 2;

    public function __construct(private readonly AgentProgress $progress) {}

    /**
     * Aceita o juramento. Aceitar de novo mantém a primeira data.
     *
     * @throws EnlistmentRefused se o alistamento já foi concluído
     */
    public function acceptOath(User $user): Agent
    {
        $agent = $user->ensureAgent();

        if (EnlistmentStep::of($agent) === EnlistmentStep::Done) {
            throw EnlistmentRefused::alreadyEnlisted();
        }

        Agent::whereKey($agent->id)->whereNull('oath_accepted_at')->update(['oath_accepted_at' => now()]);

        return $agent->refresh();
    }

    /**
     * Grava o codinome a partir do nome escolhido; o "da Silva" é acrescentado aqui.
     * Pode ser trocado até o alistamento terminar.
     *
     * @throws EnlistmentRefused antes do juramento ou depois de concluído
     */
    public function chooseCodename(User $user, string $name): Agent
    {
        $agent = $user->ensureAgent();
        $step = EnlistmentStep::of($agent);

        if ($step === EnlistmentStep::Oath) {
            throw EnlistmentRefused::oathRequired();
        }

        if ($step === EnlistmentStep::Done) {
            throw EnlistmentRefused::alreadyEnlisted();
        }

        $agent->update(['codename' => Codename::build($name)]);

        return $agent;
    }

    /**
     * Conclui o alistamento com as prioridades (lista vazia: pulou a escolha) e põe a missão
     * de entrada em andamento. Concluir de novo devolve o agente sem mudar nada: é o clique duplo.
     *
     * @param  list<Category>  $priorities
     *
     * @throws EnlistmentRefused se falta o juramento ou o codinome
     */
    public function complete(User $user, array $priorities): Agent
    {
        $agentId = $user->ensureAgent()->id;

        return DB::transaction(function () use ($user, $priorities, $agentId) {
            // Com o agente travado, dois envios ao mesmo tempo ficam em fila.
            $agent = Agent::whereKey($agentId)->lockForUpdate()->firstOrFail();
            $step = EnlistmentStep::of($agent);

            if ($step === EnlistmentStep::Done) {
                return $agent;
            }

            if ($step === EnlistmentStep::Oath) {
                throw EnlistmentRefused::oathRequired();
            }

            if ($step === EnlistmentStep::Codename) {
                throw EnlistmentRefused::codenameRequired();
            }

            $agent->update(['priorities' => $priorities, 'enlisted_at' => now()]);

            // Sem missão de entrada publicada (catálogo não carregado), conclui sem missão.
            $entry = Mission::query()->entry()->first();

            if ($entry && ! $user->missions()->where('mission_id', $entry->id)->exists()) {
                $this->progress->accept($user, $entry);
            }

            return $agent;
        });
    }

    /**
     * Muda as prioridades depois do alistamento, ou escolhe pela primeira vez quem pulou.
     *
     * @param  list<Category>  $priorities
     */
    public function changePriorities(User $user, array $priorities): Agent
    {
        $agent = $user->ensureAgent();
        $agent->update(['priorities' => $priorities]);

        return $agent;
    }
}
