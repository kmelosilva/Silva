<?php

namespace App\Services;

use App\Models\Coordinate;
use App\Models\Mission;

/**
 * A sugestão do dia: a próxima coordenada de uma missão em andamento,
 * uma missão sugerida, ou nada quando não sobra o que sugerir.
 */
final class Suggestion
{
    public const NEXT_COORDINATE = 'next_coordinate';

    public const SUGGESTED_MISSION = 'suggested_mission';

    public const NONE = 'none';

    private function __construct(
        public readonly string $type,
        public readonly ?Mission $mission,
        public readonly ?Coordinate $coordinate,
    ) {}

    public static function nextCoordinate(Mission $mission, Coordinate $coordinate): self
    {
        return new self(self::NEXT_COORDINATE, $mission, $coordinate);
    }

    public static function mission(Mission $mission): self
    {
        return new self(self::SUGGESTED_MISSION, $mission, null);
    }

    public static function none(): self
    {
        return new self(self::NONE, null, null);
    }
}
