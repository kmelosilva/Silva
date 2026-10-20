<?php

namespace App\Services;

use App\Enums\Category;
use App\Models\Coordinate;
use App\Models\Mission;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Grava o catálogo de missões no banco sem nunca apagar nada: o que está no
 * catálogo é criado ou atualizado pelo slug, e o que saiu dele é aposentado
 * (inclusive as missões geradas antes do catálogo, que não têm slug).
 * Não toca no progresso dos agentes.
 */
class MissionCatalogSync
{
    private const SLUG_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    private const SLUG_MAX_LENGTH = 80;

    /**
     * @param  array<int, array<string, mixed>>  $catalog
     * @return array{missions: int, coordinates: int, retired_missions: int, retired_coordinates: int}
     */
    public function sync(array $catalog): array
    {
        $this->validate($catalog);

        return DB::transaction(function () use ($catalog) {
            $now = now();
            $missionIds = [];
            $coordinateCount = 0;
            $retiredCoordinates = 0;

            foreach (array_values($catalog) as $index => $item) {
                $mission = Mission::updateOrCreate(
                    ['slug' => $item['slug']],
                    [
                        'title' => $item['title'],
                        'description' => $item['description'] ?? null,
                        'category' => $item['category'] ?? null,
                        'is_entry' => $item['is_entry'] ?? false,
                        'position' => $index + 1,
                        'retired_at' => null,
                    ]
                );
                $missionIds[] = $mission->id;

                $coordinateIds = [];
                foreach (array_values($item['coordinates']) as $position => $coordinate) {
                    $coordinateIds[] = Coordinate::updateOrCreate(
                        ['mission_id' => $mission->id, 'slug' => $coordinate['slug']],
                        [
                            'position' => $position + 1,
                            'title' => $coordinate['title'],
                            'content' => $this->normalize($coordinate['content'] ?? null),
                            'xp' => $coordinate['xp'],
                            'retired_at' => null,
                        ]
                    )->id;
                }
                $coordinateCount += count($coordinateIds);

                $retiredCoordinates += Coordinate::where('mission_id', $mission->id)
                    ->whereNotIn('id', $coordinateIds)
                    ->whereNull('retired_at')
                    ->update(['retired_at' => $now]);
            }

            $retiredMissions = Mission::whereNotIn('id', $missionIds)
                ->whereNull('retired_at')
                ->update(['retired_at' => $now]);

            return [
                'missions' => count($missionIds),
                'coordinates' => $coordinateCount,
                'retired_missions' => $retiredMissions,
                'retired_coordinates' => $retiredCoordinates,
            ];
        });
    }

    /**
     * Linhas seguidas viram um parágrafo; uma linha em branco separa parágrafos.
     */
    private function normalize(?string $content): ?string
    {
        if ($content === null) {
            return null;
        }

        $paragraphs = array_map(
            fn (string $paragraph) => preg_replace('/\s+/u', ' ', trim($paragraph)),
            preg_split('/\R\s*\R/u', trim($content))
        );

        $text = implode("\n\n", array_filter($paragraphs, fn (string $paragraph) => $paragraph !== ''));

        return $text === '' ? null : $text;
    }

    /**
     * Recusa o catálogo inteiro antes de gravar qualquer coisa.
     *
     * @param  array<int, array<string, mixed>>  $catalog
     */
    private function validate(array $catalog): void
    {
        if ($catalog === []) {
            throw new InvalidArgumentException('O catálogo de missões está vazio.');
        }

        $missionSlugs = [];

        foreach (array_values($catalog) as $index => $item) {
            $where = 'missão '.(is_string($item['slug'] ?? null) ? $item['slug'] : '#'.($index + 1));

            $this->assertSlug($item['slug'] ?? null, $where);
            if (in_array($item['slug'], $missionSlugs, true)) {
                throw new InvalidArgumentException("Slug repetido no catálogo: {$where}.");
            }
            $missionSlugs[] = $item['slug'];

            $this->assertTitle($item['title'] ?? null, $where);

            $isEntry = $item['is_entry'] ?? false;
            $category = $item['category'] ?? null;
            if ($isEntry && $category !== null) {
                throw new InvalidArgumentException("A {$where} é de entrada e não pode ter categoria.");
            }
            if (! $isEntry && (! is_string($category) || Category::tryFrom($category) === null)) {
                throw new InvalidArgumentException(
                    "Categoria fora da lista na {$where}: ".var_export($category, true).'.'
                );
            }

            $coordinates = $item['coordinates'] ?? [];
            if (! is_array($coordinates) || $coordinates === []) {
                throw new InvalidArgumentException("A {$where} não tem coordenadas.");
            }

            $coordinateSlugs = [];

            foreach (array_values($coordinates) as $position => $coordinate) {
                $slug = $coordinate['slug'] ?? null;
                $whereCoordinate = 'coordenada '.(is_string($slug) ? $slug : '#'.($position + 1))." da {$where}";

                $this->assertSlug($slug, $whereCoordinate);
                if (in_array($slug, $coordinateSlugs, true)) {
                    throw new InvalidArgumentException("Slug repetido na {$where}: {$whereCoordinate}.");
                }
                $coordinateSlugs[] = $slug;

                $this->assertTitle($coordinate['title'] ?? null, $whereCoordinate);

                $xp = $coordinate['xp'] ?? null;
                if (! is_int($xp) || $xp < 1) {
                    throw new InvalidArgumentException(
                        "XP precisa ser um inteiro maior que zero na {$whereCoordinate}."
                    );
                }

                $content = $coordinate['content'] ?? null;
                if ($content !== null && ! is_string($content)) {
                    throw new InvalidArgumentException("Texto inválido na {$whereCoordinate}.");
                }
            }
        }
    }

    private function assertSlug(mixed $slug, string $where): void
    {
        if (! is_string($slug) || strlen($slug) > self::SLUG_MAX_LENGTH || preg_match(self::SLUG_PATTERN, $slug) !== 1) {
            throw new InvalidArgumentException("Slug inválido na {$where}: ".var_export($slug, true).'.');
        }
    }

    private function assertTitle(mixed $title, string $where): void
    {
        if (! is_string($title) || trim($title) === '' || mb_strlen($title) > 255) {
            throw new InvalidArgumentException("Título inválido na {$where}.");
        }
    }
}
