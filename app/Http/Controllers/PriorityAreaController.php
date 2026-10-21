<?php

namespace App\Http\Controllers;

use App\Enums\Category;
use App\Models\Mission;
use App\Services\Enlistment;
use Illuminate\Http\JsonResponse;

class PriorityAreaController extends Controller
{
    /**
     * As áreas que o agente pode escolher como prioridade: todas as categorias, inclusive as
     * que ainda não têm missão, com a explicação curta, quantas missões cada uma tem nos
     * módulos e o limite de escolha.
     */
    public function index(): JsonResponse
    {
        $missionsByCategory = Mission::query()
            ->inModules()
            ->toBase()
            ->selectRaw('category, count(*) as total')
            ->groupBy('category')
            ->pluck('total', 'category');

        return response()->json(['data' => [
            'max' => Enlistment::MAX_PRIORITIES,
            'areas' => array_map(fn (Category $category) => [
                ...$category->toArray(),
                'description' => $category->description(),
                'missions_count' => (int) ($missionsByCategory[$category->value] ?? 0),
            ], Category::cases()),
        ]]);
    }
}
