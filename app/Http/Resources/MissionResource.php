<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Contrato de uma missão para a tela. Espelhado em silva-frontend/src/types/mission.ts.
 *
 * @mixin \App\Models\Mission
 */
class MissionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $coordinates = $this->publishedCoordinates;

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'description' => $this->description,
            'category' => $this->category ? [
                'key' => $this->category->value,
                'label' => $this->category->label(),
            ] : null,
            'coordinates_count' => $coordinates->count(),
            'xp_total' => (int) $coordinates->sum('xp'),
            'coordinates' => CoordinateResource::collection($coordinates),
        ];
    }
}
