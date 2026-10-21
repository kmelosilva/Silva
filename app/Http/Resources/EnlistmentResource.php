<?php

namespace App\Http\Resources;

use App\Enums\Category;
use App\Enums\EnlistmentStep;
use App\Support\Codename;
use App\Support\EnlistmentText;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * O alistamento do agente: o passo, os textos e o que já foi escolhido.
 * Espelhado em silva-frontend/src/types/enlistment.ts.
 *
 * @mixin \App\Models\Agent
 */
class EnlistmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $accountName = $this->user->name;

        return [
            'step' => EnlistmentStep::of($this->resource)->value,
            'oath' => EnlistmentText::oathFor($accountName),
            'code_of_conduct' => EnlistmentText::codeOfConduct(),
            'oath_accepted_at' => $this->oath_accepted_at?->toJSON(),
            'suggested_name' => Codename::suggestFor($accountName),
            'codename_suffix' => Codename::SUFFIX,
            'codename' => $this->codename,
            'priorities' => $this->priorityAreas()->map(fn (Category $category) => $category->toArray())->values()->all(),
            'enlisted_at' => $this->enlisted_at?->toJSON(),
        ];
    }
}
