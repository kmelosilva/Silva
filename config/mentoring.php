<?php

/*
 * A mentoria dos submentores (spec mentoria_submentores_2026-10-21, D-10).
 *
 * A IA nasce desligada: um clone do repositório público não tenta chamar a API sem chave,
 * e todo momento da mentoria funciona com o texto escrito. Ela só liga depois da spec de
 * segredos e da avaliação (php artisan mentoring:eval), com o aval do Kayque.
 */

return [

    // Chave geral. Desligada, toda resposta à reflexão é o texto escrito do submentor.
    'enabled' => (bool) env('MENTORING_AI_ENABLED', false),

    // O mais barato da Anthropic (D-05).
    'model' => 'claude-haiku-5-5',

    // Teto do mês, somado no calendário de Brasília. Atingido, vale o texto escrito.
    'monthly_budget_usd' => (float) env('MENTORING_MONTHLY_BUDGET_USD', 5),

    // Limite de cada chamada, sem nova tentativa dentro do pedido do agente.
    'timeout_seconds' => 6,

    // Dólares por milhão de tokens. Precisam acompanhar a tabela da Anthropic: se ela mudar,
    // o teto passa a contar errado até este arquivo ser atualizado.
    'prices' => [
        'input_per_mtok' => 0.10,
        'output_per_mtok' => 0.50,
    ],

    // Dias sem cumprir coordenada da missão em andamento até a boas-vindas da volta (RF-06).
    'welcome_back_days' => 7,

];
