<?php

namespace App\Http\Resources;

use App\Support\LevelCurve;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * O painel do agente. Espelhado em silva-frontend/src/types/agent.ts.
 *
 * @mixin \App\Models\Agent
 */
class AgentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->user->name,
            'level' => $this->level,
            'xp' => $this->xp,
            'level_xp' => LevelCurve::xpToReach($this->level),
            'next_level_xp' => LevelCurve::xpToReach($this->level + 1),
            'missions_completed' => $this->user->missions()->completed()->count(),
            'streak' => [
                'current' => $this->currentStreak(),
                'best' => $this->streak_best,
            ],
        ];
    }
}
