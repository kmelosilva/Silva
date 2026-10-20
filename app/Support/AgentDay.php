<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * O dia do agente, contado no horário de Brasília. A aplicação continua em UTC;
 * só a sequência de dias e a sugestão do dia usam este calendário.
 */
final class AgentDay
{
    public const TIMEZONE = 'America/Sao_Paulo';

    /**
     * Hoje, como data (AAAA-MM-DD).
     */
    public static function today(): string
    {
        return CarbonImmutable::now(self::TIMEZONE)->toDateString();
    }

    /**
     * Ontem, como data (AAAA-MM-DD).
     */
    public static function yesterday(): string
    {
        return CarbonImmutable::now(self::TIMEZONE)->subDay()->toDateString();
    }
}
