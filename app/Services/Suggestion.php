<?php

namespace App\Services;

use App\Enums\Category;
use App\Models\Coordinate;
use App\Models\Mission;

/**
 * A sugestão do dia: a próxima coordenada de uma missão em andamento,
 * uma missão sugerida, ou nada quando não sobra o que sugerir. Quando a sugestão não é
 * de continuar, ela diz também quais áreas de prioridade ficaram sem missão.
 */
final class Suggestion
{
    public const NEXT_COORDINATE = 'next_coordinate';

    public const SUGGESTED_MISSION = 'suggested_mission';

    public const NONE = 'none';

    /**
     * @param  list<Category>  $unavailablePriorities
     */
    private function __construct(
        public readonly string $type,
        public readonly ?Mission $mission,
        public readonly ?Coordinate $coordinate,
        public readonly array $unavailablePriorities = [],
    ) {}

    public static function nextCoordinate(Mission $mission, Coordinate $coordinate): self
    {
        return new self(self::NEXT_COORDINATE, $mission, $coordinate);
    }

    /**
     * @param  list<Category>  $unavailablePriorities
     */
    public static function mission(Mission $mission, array $unavailablePriorities = []): self
    {
        return new self(self::SUGGESTED_MISSION, $mission, null, $unavailablePriorities);
    }

    /**
     * @param  list<Category>  $unavailablePriorities
     */
    public static function none(array $unavailablePriorities = []): self
    {
        return new self(self::NONE, null, null, $unavailablePriorities);
    }
}
