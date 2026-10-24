<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Uma chamada à IA: tokens, custo, resultado e duração. Nunca o texto do agente nem a
 * resposta. É a soma do custo destas linhas que mede o teto do mês.
 */
class AiCall extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'model',
        'input_tokens',
        'output_tokens',
        'cost_usd',
        'outcome',
        'duration_ms',
    ];

    protected function casts(): array
    {
        return [
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'cost_usd' => 'decimal:6',
            'duration_ms' => 'integer',
        ];
    }
}
