<?php

namespace App\Http\Controllers;

use App\Http\Resources\DailySuggestionResource;
use App\Services\DailySuggestion;
use Illuminate\Http\Request;

class DailySuggestionController extends Controller
{
    /**
     * O que fazer hoje: a próxima coordenada da missão em andamento, ou uma missão sugerida.
     */
    public function show(Request $request, DailySuggestion $suggestion): DailySuggestionResource
    {
        return new DailySuggestionResource($suggestion->for($request->user()));
    }
}
