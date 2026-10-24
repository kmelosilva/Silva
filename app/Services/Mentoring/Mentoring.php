<?php

namespace App\Services\Mentoring;

use App\Enums\Category;
use App\Models\Agent;
use App\Models\Mission;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * A mentoria dos submentores do Ninho: quem o agente conhece, a reflexão sobre a
 * coordenada cumprida e a resposta do submentor. Nunca escreve no XP, no nível nem na
 * sequência: isso é só do AgentProgress.
 */
class Mentoring
{
    /**
     * As áreas que têm submentor: as que têm pelo menos uma missão nos módulos, na ordem
     * do Category. Área sem missão não apresenta submentor (RN-03).
     *
     * @return list<Category>
     */
    public function areasWithSubmentor(): array
    {
        $withMissions = Mission::query()->inModules()->pluck('category')->all();

        return array_values(array_filter(
            Category::cases(),
            fn (Category $area) => in_array($area, $withMissions, true),
        ));
    }

    /**
     * Marca que o agente conheceu o submentor da área. Repetir não muda nada.
     */
    public function meet(User $user, Category $area): void
    {
        $agentId = $user->ensureAgent()->id;

        DB::transaction(function () use ($agentId, $area) {
            // Travado, para duas apresentações ao mesmo tempo não apagarem uma à outra.
            $agent = Agent::whereKey($agentId)->lockForUpdate()->firstOrFail();
            $met = $agent->metSubmentors();

            if (! $met->contains($area)) {
                $agent->update(['submentors_met' => $met->push($area)->all()]);
            }
        });
    }
}
