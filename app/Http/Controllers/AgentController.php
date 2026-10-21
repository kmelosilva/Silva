<?php

namespace App\Http\Controllers;

use App\Http\Requests\PrioritiesRequest;
use App\Http\Resources\AgentResource;
use App\Services\Enlistment;
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

    /**
     * Muda as áreas de prioridade (ou escolhe, para quem pulou no alistamento); devolve o painel.
     */
    public function priorities(PrioritiesRequest $request, Enlistment $enlistment): JsonResponse
    {
        $agent = $enlistment->changePriorities($request->user(), $request->priorities());

        return (new AgentResource($agent))->response()->setStatusCode(200);
    }
}
