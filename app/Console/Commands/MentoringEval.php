<?php

namespace App\Console\Commands;

use App\Models\AiCall;
use App\Models\Coordinate;
use App\Models\Mission;
use App\Models\Reflection;
use App\Services\Mentoring\AiBudget;
use App\Services\Mentoring\Mentoring;
use App\Services\Mentoring\MentorModel;
use App\Services\Mentoring\MentorPrompt;
use App\Services\Mentoring\MentorReply;
use App\Support\CareSignals;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * A avaliação da resposta do submentor antes de ir ao público (spec mentoria_submentores,
 * D-11): roda as 36 reflexões de tests/Fixtures/mentoring_eval.php pelas mesmas barreiras
 * da tela, com a API real, sem gravar reflexão, e escreve um relatório para o Kayque ler.
 * Cada chamada entra em ai_calls, sem agente, e conta no teto do mês.
 */
class MentoringEval extends Command
{
    protected $signature = 'mentoring:eval
        {--output= : Pasta do relatório (padrão: storage/app/mentoring-eval)}';

    protected $description = 'Roda o conjunto de avaliação da resposta do submentor com a API real e escreve o relatório';

    private const CODENAME = 'Agente de Teste da Silva';

    public function handle(Mentoring $mentoring, AiBudget $budget): int
    {
        if ((string) config('services.anthropic.key') === '') {
            $this->error('Sem ANTHROPIC_API_KEY no .env: a avaliação chama a API de verdade e fica para depois da spec de segredos.');

            return self::FAILURE;
        }

        $cases = require base_path('tests/Fixtures/mentoring_eval.php');
        $missing = $this->missingCoordinates($cases);
        if ($missing !== []) {
            $this->error('Coordenadas do conjunto que não estão no banco: '.implode(', ', $missing).'. Rode a carga do catálogo antes.');

            return self::FAILURE;
        }

        $enabled = config('mentoring.enabled');
        $model = app(MentorModel::class);
        $reflectionsBefore = Reflection::count();

        try {
            // Só para esta rodada: o .env continua como está.
            config(['mentoring.enabled' => true]);
            $rows = $this->evaluate($cases, $mentoring, $this->recordReplies($model));
        } finally {
            config(['mentoring.enabled' => $enabled]);
            app()->instance(MentorModel::class, $model);
        }

        if (Reflection::count() !== $reflectionsBefore) {
            $this->error('A avaliação gravou reflexão, e não deveria.');

            return self::FAILURE;
        }

        $directory = $this->option('output') ?: storage_path('app/mentoring-eval');
        File::ensureDirectoryExists($directory);
        $path = $directory.'/'.now()->format('Y-m-d-His').'.md';
        File::put($path, $this->report($rows, $mentoring, $budget));

        $this->info("Relatório em {$path}");

        return self::SUCCESS;
    }

    /**
     * @param  list<array<string, string>>  $cases
     * @return list<array{case: array<string, string>, answer: \App\Services\Mentoring\Answer, reply: ?MentorReply, call: ?AiCall}>
     */
    private function evaluate(array $cases, Mentoring $mentoring, object $recorder): array
    {
        $rows = [];

        foreach ($cases as $case) {
            $mission = Mission::where('slug', $case['mission'])->firstOrFail();
            $coordinate = $mission->coordinates()->where('slug', $case['coordinate'])->firstOrFail();
            $callsBefore = AiCall::count();
            $recorder->last = null;

            $answer = $mentoring->answer($mission, $coordinate, $case['text'], self::CODENAME, "eval|{$case['id']}");

            $rows[] = [
                'case' => $case,
                'answer' => $answer,
                'reply' => $recorder->last,
                'call' => AiCall::count() > $callsBefore ? AiCall::latest('id')->first() : null,
            ];
            $this->line(sprintf('%-12s %-8s → %s', $case['id'], $case['expect'], $answer->source));
        }

        return $rows;
    }

    /**
     * Põe no container um modelo que guarda a última resposta, para o relatório mostrar o
     * que a IA escreveu mesmo quando a checagem trocou pelo texto escrito.
     */
    private function recordReplies(MentorModel $model): object
    {
        $recorder = new class
        {
            public ?MentorReply $last = null;
        };

        app()->instance(MentorModel::class, new class($model, $recorder) implements MentorModel
        {
            public function __construct(private readonly MentorModel $model, private readonly object $recorder) {}

            public function reply(MentorPrompt $prompt): MentorReply
            {
                return $this->recorder->last = $this->model->reply($prompt);
            }
        });

        return $recorder;
    }

    /**
     * @param  list<array<string, string>>  $cases
     * @return list<string>
     */
    private function missingCoordinates(array $cases): array
    {
        return collect($cases)
            ->reject(fn (array $case) => Coordinate::where('slug', $case['coordinate'])
                ->whereHas('mission', fn ($query) => $query->where('slug', $case['mission']))
                ->exists())
            ->map(fn (array $case) => "{$case['mission']}/{$case['coordinate']}")
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<array{case: array<string, string>, answer: \App\Services\Mentoring\Answer, reply: ?MentorReply, call: ?AiCall}>  $rows
     */
    private function report(array $rows, Mentoring $mentoring, AiBudget $budget): string
    {
        $crises = array_filter($rows, fn (array $row) => $row['case']['expect'] === 'care');
        $replies = array_filter($rows, fn (array $row) => $row['case']['expect'] === 'reply');
        $cared = array_filter($crises, fn (array $row) => $row['answer']->source === Reflection::SOURCE_CARE);
        $byList = array_filter($crises, fn (array $row) => CareSignals::found($row['case']['text']));
        $falseCare = array_filter($replies, fn (array $row) => $row['answer']->source === Reflection::SOURCE_CARE);
        $aiTexts = array_filter($rows, fn (array $row) => $row['reply']?->text !== null);
        $aiViolations = array_filter($aiTexts, fn (array $row) => $mentoring->replyProblems($row['reply']->text) !== []);
        $calls = array_values(array_filter(array_column($rows, 'call')));
        $cost = array_sum(array_map(fn (AiCall $call) => (float) $call->cost_usd, $calls));
        $outcomes = array_count_values(array_map(fn (AiCall $call) => $call->outcome, $calls));
        ksort($outcomes);

        $lines = [
            '# Avaliação da resposta do submentor — '.now()->format('d/m/Y H:i'),
            '',
            'Spec `mentoria_submentores_2026-10-21`, CS-02 e CS-03. Modelo `'.config('mentoring.model').'`, '
                .count($rows).' reflexões de `tests/Fixtures/mentoring_eval.php`, pelas mesmas barreiras da tela.',
            '',
            '## Resumo',
            '',
            '| O quê | Resultado |',
            '|-------|-----------|',
            '| Crises com a mensagem de cuidado | **'.count($cared).' de '.count($crises).'** ('.count($byList).' pela lista, sem chamar a IA) |',
            '| Reflexões comuns que receberam cuidado sem precisar | '.count($falseCare).' de '.count($replies).' |',
            '| Respostas da IA com termo proibido, conselho, saudação com gênero ou forma errada | '.count($aiViolations).' de '.count($aiTexts)
                .($aiViolations !== [] ? ' (trocadas pelo texto escrito antes de chegar ao agente)' : '').' |',
            '| Chamadas à IA, por resultado | '.(count($calls) === 0 ? 'nenhuma' : collect($outcomes)->map(fn ($n, $outcome) => "{$outcome}: {$n}")->implode(', ')).' |',
            '| Custo da rodada | US$ '.$this->dollars($cost).' |',
            '| Custo médio por chamada (CS-03: até US$ 0,001) | US$ '.$this->dollars(count($calls) ? $cost / count($calls) : 0).' |',
            '| Gasto do mês, com esta rodada | US$ '.$this->dollars($budget->spentThisMonth()).' de US$ '.$this->dollars((float) config('mentoring.monthly_budget_usd'), 2).' |',
            '',
            'A bronca e o tom não têm checagem automática: leia cada resposta abaixo.',
            '',
            '## Casos',
        ];

        foreach ($rows as $row) {
            $case = $row['case'];
            $reply = $row['reply'];
            $call = $row['call'];
            $ok = $case['expect'] === 'care'
                ? $row['answer']->source === Reflection::SOURCE_CARE
                : $row['answer']->source !== Reflection::SOURCE_CARE;

            $lines[] = '';
            $lines[] = "### {$case['id']} — ".($ok ? 'ok' : '**ATENÇÃO**');
            $lines[] = '';
            $lines[] = "- **Missão:** `{$case['mission']}` / `{$case['coordinate']}`; **armadilha:** {$case['trap']}; **esperado:** {$case['expect']}";
            $lines[] = '- **Reflexão:** '.$case['text'];
            $lines[] = '- **Na tela:** '.$row['answer']->source.' — '.$this->quote($row['answer']->text);
            if ($reply === null) {
                $lines[] = '- **IA:** não foi chamada'.(CareSignals::found($case['text']) ? ' (a lista de risco pegou antes)' : '');
            } else {
                $problems = $reply->text === null ? [] : $mentoring->replyProblems($reply->text);
                $lines[] = '- **IA:** '.$reply->outcome.($reply->text !== null ? ' — '.$this->quote($reply->text) : '');
                $lines[] = '- **Checagem da resposta da IA:** '.($problems === [] ? 'nada a apontar' : implode('; ', $problems));
            }
            if ($call !== null) {
                $lines[] = "- **Registro:** {$call->outcome}, {$call->input_tokens} tokens de entrada, {$call->output_tokens} de saída, US$ "
                    .$this->dollars((float) $call->cost_usd).", {$call->duration_ms} ms";
            }
        }

        return implode("\n", $lines)."\n";
    }

    private function dollars(float $value, int $decimals = 6): string
    {
        return number_format($value, $decimals, ',', '.');
    }

    private function quote(string $text): string
    {
        return '"'.preg_replace('/\s+/u', ' ', trim($text)).'"';
    }
}
