<?php

namespace App\Services\Mentoring;

use App\Models\AiCall;
use App\Support\AgentDay;

/**
 * O teto do mês da IA (RNF-03) e o registro de cada chamada, com os tokens e o custo,
 * nunca o texto. O mês é o do calendário de Brasília, e o custo usa os preços da
 * configuração.
 */
class AiBudget
{
    public function spentThisMonth(): float
    {
        return (float) AiCall::where('created_at', '>=', AgentDay::startOfMonth())->sum('cost_usd');
    }

    public function reached(): bool
    {
        return $this->spentThisMonth() >= (float) config('mentoring.monthly_budget_usd');
    }

    /**
     * @param  string  $outcome  o resultado da chamada, ou "blocked" quando a checagem barrou a resposta
     */
    public function record(MentorReply $reply, string $outcome, ?int $userId): AiCall
    {
        return AiCall::create([
            'user_id' => $userId,
            'model' => $reply->model,
            'input_tokens' => $reply->inputTokens,
            'output_tokens' => $reply->outputTokens,
            'cost_usd' => $this->cost($reply->inputTokens, $reply->outputTokens),
            'outcome' => $outcome,
            'duration_ms' => $reply->durationMs,
        ]);
    }

    public function cost(int $inputTokens, int $outputTokens): float
    {
        $prices = config('mentoring.prices');

        return ($inputTokens * $prices['input_per_mtok'] + $outputTokens * $prices['output_per_mtok']) / 1_000_000;
    }
}
