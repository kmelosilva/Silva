<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * O dia do agente, contado no horário de Brasília. A aplicação continua em UTC;
 * só a sequência de dias, a sugestão do dia e a boas-vindas da volta usam este calendário.
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

    /**
     * Quantos dias do calendário de Brasília se passaram desde o momento até hoje.
     */
    public static function daysSince(CarbonInterface $moment): int
    {
        $then = CarbonImmutable::instance($moment)->setTimezone(self::TIMEZONE)->startOfDay();

        return (int) $then->diffInDays(CarbonImmutable::now(self::TIMEZONE)->startOfDay());
    }
}
