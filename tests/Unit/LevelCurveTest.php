<?php

namespace Tests\Unit;

use App\Support\LevelCurve;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class LevelCurveTest extends TestCase
{
    public function test_rf06_passar_do_nivel_n_para_o_seguinte_custa_50_vezes_n(): void
    {
        $this->assertSame(
            [0, 50, 150, 300, 500, 750],
            array_map(fn (int $level) => LevelCurve::xpToReach($level), range(1, 6))
        );

        foreach (range(1, 20) as $level) {
            $this->assertSame(
                50 * $level,
                LevelCurve::xpToReach($level + 1) - LevelCurve::xpToReach($level),
                "Passar do nível {$level} para o seguinte deveria custar ".(50 * $level).' XP.'
            );
        }
    }

    #[DataProvider('xpAndLevel')]
    public function test_rf06_nivel_alcancado_com_o_xp(int $xp, int $level): void
    {
        $this->assertSame($level, LevelCurve::levelFor($xp));
    }

    /**
     * @return array<string, array{int, int}>
     */
    public static function xpAndLevel(): array
    {
        return [
            'sem XP' => [0, 1],
            'um antes do nível 2' => [49, 1],
            'exatamente o nível 2' => [50, 2],
            'um antes do nível 3' => [149, 2],
            'exatamente o nível 3' => [150, 3],
            'o catálogo de hoje (200 XP)' => [200, 3],
            'exatamente o nível 4' => [300, 4],
            'muito XP de uma vez' => [5000, 14],
        ];
    }

    public function test_rf06_nivel_abaixo_de_1_e_tratado_como_1(): void
    {
        $this->assertSame(0, LevelCurve::xpToReach(0));
    }
}
