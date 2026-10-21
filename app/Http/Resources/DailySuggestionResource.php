<?php

namespace App\Http\Resources;

use App\Services\Suggestion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A sugestão do dia. Espelhado em silva-frontend/src/types/agent.ts.
 *
 * @property Suggestion $resource
 */
class DailySuggestionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $suggestion = $this->resource;
        $mission = $suggestion->mission;
        $coordinate = $suggestion->coordinate;

        return [
            'type' => $suggestion->type,
            'mission' => $mission ? [
                'slug' => $mission->slug,
                'title' => $mission->title,
                'category' => $mission->category?->toArray(),
            ] : null,
            'coordinate' => $coordinate ? [
                'slug' => $coordinate->slug,
                'position' => $coordinate->position,
                'title' => $coordinate->title,
            ] : null,
        ];
    }
}
