<?php

namespace App\Http\Resources;

use App\Enums\Category;
use App\Support\MentoringText;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Um submentor do Ninho, como a tela o mostra. Espelhado em silva-frontend/src/types/mentoring.ts.
 * A voz e as respostas escritas ficam de fora: só a API as usa.
 *
 * @property array{area: Category, met: bool} $resource
 */
class SubmentorResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $area = $this->resource['area'];
        $submentor = MentoringText::submentor($area);

        return [
            'area' => $area->toArray(),
            'codename' => $submentor['codename'],
            'animal' => $submentor['animal'],
            'power_up' => $submentor['power_up'],
            'intro' => $submentor['intro'],
            'closing' => $submentor['closing'],
            'welcome_backs' => $submentor['welcome_backs'],
            'notice' => MentoringText::noticeFor($area),
            'care_footer' => MentoringText::care()['footer'],
            'met' => $this->resource['met'],
        ];
    }
}
