<?php

namespace App\Support;

/**
 * A curva de nível do agente: passar do nível N para o N+1 custa 50 × N XP.
 * É o único lugar que conhece a curva; o front recebe os limites prontos.
 */
final class LevelCurve
{
    public const XP_STEP = 50;

    /**
     * XP acumulado necessário para chegar ao nível: 0, 50, 150, 300, 500...
     */
    public static function xpToReach(int $level): int
    {
        $level = max(1, $level);

        return intdiv(self::XP_STEP * $level * ($level - 1), 2);
    }

    /**
     * O maior nível alcançado com esse XP.
     */
    public static function levelFor(int $xp): int
    {
        $level = 1;

        while (self::xpToReach($level + 1) <= $xp) {
            $level++;
        }

        return $level;
    }
}
