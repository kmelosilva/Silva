<?php

namespace App\Http\Controllers;

use App\Http\Resources\AgentResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentController extends Controller
{
    /**
     * O painel do agente da conta conectada; cria o agente se faltar.
     */
    public function show(Request $request): JsonResponse
    {
        // 200 sempre: o Resource responderia 201 quando o agente acabou de ser criado.
        return (new AgentResource($request->user()->ensureAgent()))->response()->setStatusCode(200);
    }
}
