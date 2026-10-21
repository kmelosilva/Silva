<?php

namespace App\Http\Resources;

use App\Models\Coordinate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Uma coordenada para a tela. Espelhado em silva-frontend/src/types/mission.ts.
 *
 * @mixin \App\Models\Coordinate
 */
class CoordinateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'position' => $this->position,
            'title' => $this->title,
            'content' => $this->content,
            'xp' => $this->xp,
            // Só nas coordenadas com esforço físico; a tela mostra acima do texto.
            'care_notice' => $this->physical_effort ? Coordinate::CARE_NOTICE : null,
        ];
    }
}
