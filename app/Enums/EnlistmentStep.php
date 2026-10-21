<?php

namespace App\Enums;

use App\Models\Agent;

/**
 * O passo do alistamento, sempre calculado a partir dos campos do agente, nunca guardado.
 * Retomar o alistamento é só perguntar o passo de novo.
 */
enum EnlistmentStep: string
{
    case Oath = 'oath';
    case Codename = 'codename';
    case Priorities = 'priorities';
    case Done = 'done';

    public static function of(Agent $agent): self
    {
        return match (true) {
            $agent->enlisted_at !== null => self::Done,
            $agent->oath_accepted_at === null => self::Oath,
            $agent->codename === null => self::Codename,
            default => self::Priorities,
        };
    }
}
