<?php

namespace App\Http\Controllers;

use App\Enums\Category;
use App\Http\Resources\SubmentorResource;
use App\Services\Mentoring\Mentoring;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SubmentorController extends Controller
{
    public function __construct(private readonly Mentoring $mentoring) {}

    /**
     * Os submentores das áreas com missão, com o que o agente já conheceu.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $met = $request->user()->ensureAgent()->metSubmentors();

        return SubmentorResource::collection(array_map(
            fn (Category $area) => ['area' => $area, 'met' => $met->contains($area)],
            $this->mentoring->areasWithSubmentor(),
        ));
    }

    /**
     * Marca a apresentação do submentor como vista. Área sem submentor dá 404.
     */
    public function met(Request $request, string $area): JsonResponse
    {
        $category = Category::tryFrom($area);

        abort_if(
            ! $category || ! in_array($category, $this->mentoring->areasWithSubmentor(), true),
            404,
            'Submentor não encontrado.',
        );

        $this->mentoring->meet($request->user(), $category);

        return response()->json(['data' => ['area' => $category->value, 'met' => true]]);
    }
}
