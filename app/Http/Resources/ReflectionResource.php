<?php

namespace App\Http\Resources;

use App\Support\MentoringText;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Uma reflexão do agente, com a resposta. Espelhado em silva-frontend/src/types/mentoring.ts.
 * Os títulos vêm junto para a reflexão continuar legível mesmo se a missão sair do catálogo.
 *
 * @mixin \App\Models\Reflection
 */
class ReflectionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $coordinate = $this->completion->coordinate;
        $mission = $coordinate->mission;

        return [
            'id' => $this->id,
            'mission_slug' => $mission->slug,
            'mission_title' => $mission->title,
            'coordinate_slug' => $coordinate->slug,
            'coordinate_title' => $coordinate->title,
            'text' => $this->text,
            'reply' => $this->reply,
            'reply_source' => $this->reply_source,
            'submentor' => [
                'area' => $mission->category->value,
                'codename' => MentoringText::submentor($mission->category)['codename'],
            ],
            'created_at' => $this->created_at?->toJSON(),
        ];
    }
}
