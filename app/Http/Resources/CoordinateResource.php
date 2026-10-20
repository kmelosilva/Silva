<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
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
        ];
    }
}
