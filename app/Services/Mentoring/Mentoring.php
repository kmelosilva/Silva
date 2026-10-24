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
use App\Support\AdviceRules;
use App\Support\CareSignals;
use App\Support\ContentRules;
use App\Support\MentoringText;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

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

    /** A forma da resposta da IA (D-07, etapa 4). */
    private const REPLY_MAX_SENTENCES = 3;

    private const REPLY_MAX_CHARS = 400;

    public function __construct(private readonly AiBudget $budget) {}

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
                    // Antes do alistamento não há codinome; o nome da conta nunca vai para a IA (RNF-06).
                    $user->ensureAgent()->codename ?? 'agente',
                    "{$user->id}|{$coordinate->id}",
                    aiAlreadyCalled: $completion->ai_replied_at !== null,
                    userId: $user->id,
                );

                return DB::transaction(function () use ($user, $completion, $text, $answer) {
                    if ($answer->calledAi) {
                        // Fica mesmo se a reflexão for apagada: uma chamada por coordenada.
                        $completion->update(['ai_replied_at' => now()]);
                    }

                    return Reflection::create([
                        'user_id' => $user->id,
                        'coordinate_completion_id' => $completion->id,
                        'text' => $text,
                        'reply' => $answer->text,
                        'reply_source' => $answer->source,
                    ]);
                });
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
     * A resposta à reflexão, passando pelas cinco barreiras na ordem (D-07). Não grava a
     * reflexão: o comando de avaliação usa as mesmas barreiras. Só registra a chamada à IA,
     * quando ela acontece, em ai_calls.
     *
     * @param  string  $writtenKey  escolhe a resposta escrita: o mesmo caso dá sempre o mesmo texto
     * @param  bool  $aiAlreadyCalled  a IA já foi chamada para esta coordenada (RN-06)
     */
    public function answer(
        Mission $mission,
        Coordinate $coordinate,
        string $text,
        string $codename,
        string $writtenKey,
        bool $aiAlreadyCalled = false,
        ?int $userId = null,
    ): Answer {
        // 1. Sinal de risco: cuidado, sem chamar a IA. Funciona com a IA desligada ou sem verba.
        if (CareSignals::found($text)) {
            return Answer::care(MentoringText::care()['message']);
        }

        // 2. Chave geral, chave da API, chamada já feita nesta coordenada e teto do mês.
        if (
            ! config('mentoring.enabled')
            || (string) config('services.anthropic.key') === ''
            || $aiAlreadyCalled
            || $this->budget->reached()
        ) {
            return $this->writtenAnswer($mission->category, $codename, $writtenKey);
        }

        // 3. A chamada. O modelo é pedido só agora: montar o cliente custa, e os outros
        // caminhos não precisam dele.
        $prompt = MentorPrompt::for($mission, $coordinate, $codename, $text);
        $startedAt = hrtime(true);

        try {
            $reply = app(MentorModel::class)->reply($prompt);
        } catch (Throwable $exception) {
            // 5. Falha que escapou do modelo. Só a classe vai para o log, nunca o texto.
            Log::warning('Mentoria: a chamada à IA falhou.', ['exception' => $exception::class]);
            $reply = new MentorReply(
                MentorReply::ERROR, null, (string) config('mentoring.model'), 0, 0,
                (int) round((hrtime(true) - $startedAt) / 1_000_000),
            );
        }

        // 4. A resposta passa pelas regras da Agência antes de chegar ao agente.
        $outcome = $reply->outcome === MentorReply::REPLY && ! $this->acceptable((string) $reply->text)
            ? 'blocked'
            : $reply->outcome;
        $this->budget->record($reply, $outcome, $userId);

        return match ($outcome) {
            MentorReply::REPLY => Answer::ai($reply->text),
            MentorReply::CARE => Answer::care(MentoringText::care()['message'], calledAi: true),
            // 5. Barrada, recusada, fora do esquema, tempo esgotado ou erro: o texto escrito.
            default => $this->writtenAnswer($mission->category, $codename, $writtenKey, calledAi: true),
        };
    }

    /**
     * A resposta da IA só passa sem termo proibido, sem conselho profissional, sem saudação
     * com gênero e na forma combinada: não vazia, até 3 frases e até 400 caracteres.
     */
    private function acceptable(string $reply): bool
    {
        $reply = trim($reply);
        $sentences = preg_split('/(?<=[.!?…])\s+/u', $reply, -1, PREG_SPLIT_NO_EMPTY);

        return $reply !== ''
            && mb_strlen($reply) <= self::REPLY_MAX_CHARS
            && count($sentences) <= self::REPLY_MAX_SENTENCES
            && ContentRules::forbiddenTermsIn($reply) === []
            && AdviceRules::termsIn($reply) === []
            && preg_match(MentoringText::GENDERED_GREETING, $reply) !== 1;
    }

    private function writtenAnswer(Category $area, string $codename, string $key, bool $calledAi = false): Answer
    {
        $replies = MentoringText::submentor($area)['written_replies'];
        $reply = $replies[crc32($key) % count($replies)];

        return Answer::written(strtr($reply, [':codinome' => $codename]), $calledAi);
    }
}
