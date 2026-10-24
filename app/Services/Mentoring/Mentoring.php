<?php

namespace App\Services\Mentoring;

use App\Enums\Category;
use App\Exceptions\MentoringRefused;
use App\Models\Agent;
use App\Models\Coordinate;
use App\Models\CoordinateCompletion;
use App\Models\Mission;
use App\Models\Reflection;
use App\Models\User;
use App\Support\CareSignals;
use App\Support\MentoringText;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * A mentoria dos submentores do Ninho: quem o agente conhece, a reflexão sobre a
 * coordenada cumprida e a resposta do submentor. Nunca escreve no XP, no nível nem na
 * sequência: isso é só do AgentProgress.
 */
class Mentoring
{
    /**
     * A trava por conclusão dura mais que a chamada à IA (6 s), para o segundo envio de um
     * clique duplo esperar o primeiro gravar, em vez de chamar a IA de novo.
     */
    private const LOCK_SECONDS = 20;

    private const LOCK_WAIT_SECONDS = 15;

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

    /**
     * Guarda a reflexão do agente sobre uma coordenada cumprida, com a resposta do submentor.
     * Se já existe reflexão nessa coordenada, devolve a mesma, sem gerar outra resposta
     * (wasRecentlyCreated diz qual foi o caso).
     *
     * @throws MentoringRefused se o agente não cumpriu a coordenada
     */
    public function reflect(User $user, Mission $mission, Coordinate $coordinate, string $text): Reflection
    {
        $completion = CoordinateCompletion::where('user_id', $user->id)
            ->where('coordinate_id', $coordinate->id)
            ->first();

        if (! $completion) {
            throw MentoringRefused::requiresCompletion();
        }

        return Cache::lock("mentoring:reflection:{$completion->id}", self::LOCK_SECONDS)
            ->block(self::LOCK_WAIT_SECONDS, function () use ($user, $mission, $coordinate, $text, $completion) {
                $existing = $completion->reflection()->first();
                if ($existing) {
                    return $existing;
                }

                $answer = $this->answer(
                    $mission,
                    $coordinate,
                    $text,
                    $user->ensureAgent()->codename ?? $user->name,
                    "{$user->id}|{$coordinate->id}",
                );

                return Reflection::create([
                    'user_id' => $user->id,
                    'coordinate_completion_id' => $completion->id,
                    'text' => $text,
                    'reply' => $answer->text,
                    'reply_source' => $answer->source,
                ]);
            });
    }

    /**
     * As reflexões do agente, na ordem das missões e das coordenadas; só de uma missão,
     * se o slug vier. Missão ou coordenada que saiu do catálogo continua aparecendo.
     *
     * @return Collection<int, Reflection>
     */
    public function reflectionsOf(User $user, ?string $missionSlug = null): Collection
    {
        return Reflection::query()
            ->select('reflections.*')
            ->join('coordinate_completions', 'coordinate_completions.id', '=', 'reflections.coordinate_completion_id')
            ->join('coordinates', 'coordinates.id', '=', 'coordinate_completions.coordinate_id')
            ->join('missions', 'missions.id', '=', 'coordinates.mission_id')
            ->where('reflections.user_id', $user->id)
            ->when($missionSlug !== null, fn ($query) => $query->where('missions.slug', $missionSlug))
            ->orderBy('missions.position')
            ->orderBy('coordinates.position')
            ->orderBy('reflections.id')
            ->with('completion.coordinate.mission')
            ->get();
    }

    /**
     * Apaga uma reflexão do agente, de verdade. A marca de que a IA já respondeu fica na
     * conclusão, e o XP, o nível, a sequência e o progresso ficam como estão.
     *
     * @return bool falso quando a reflexão não existe ou é de outro agente
     */
    public function forget(User $user, int $reflectionId): bool
    {
        return Reflection::where('user_id', $user->id)->whereKey($reflectionId)->delete() === 1;
    }

    /**
     * Apaga todas as reflexões do agente.
     */
    public function forgetAll(User $user): void
    {
        Reflection::where('user_id', $user->id)->delete();
    }

    /**
     * A resposta à reflexão, passando pelas barreiras na ordem (D-07). Não grava nada: o
     * comando de avaliação usa as mesmas barreiras.
     *
     * @param  string  $writtenKey  escolhe a resposta escrita: o mesmo caso dá sempre o mesmo texto
     */
    public function answer(Mission $mission, Coordinate $coordinate, string $text, string $codename, string $writtenKey): Answer
    {
        // 1. Sinal de risco: cuidado, sem chamar a IA.
        if (CareSignals::found($text)) {
            return Answer::care(MentoringText::care()['message']);
        }

        // 2. IA desligada: o texto escrito do submentor.
        return $this->writtenAnswer($mission->category, $codename, $writtenKey);
    }

    private function writtenAnswer(Category $area, string $codename, string $key): Answer
    {
        $replies = MentoringText::submentor($area)['written_replies'];
        $reply = $replies[crc32($key) % count($replies)];

        return Answer::written(strtr($reply, [':codinome' => $codename]));
    }
}
