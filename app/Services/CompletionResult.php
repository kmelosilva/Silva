<?php

namespace App\Services;

use App\Models\Agent;

/**
 * O que aconteceu ao cumprir uma coordenada, para a tela se atualizar sem buscar de novo.
 */
final class CompletionResult
{
    public function __construct(
        public readonly MissionProgress $progress,
        public readonly Agent $agent,
        public readonly int $xpGained,
        public readonly int $levelsGained,
        public readonly bool $missionCompleted,
        public readonly bool $alreadyCompleted,
    ) {}
}
